<?php
/**
 * Файл: inc/api-service.php
 * Назначение: Работа с внешними API (chips.ru, ЦБ РФ), параллельные запросы
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Получает данные о товарах из API chips.ru
 * @param string|array $input
 * @return array
 */
function get_products_data($input) {
    $client_ip = _get_client_ip();
    $articleToQty = _parse_input_to_articles($input);

    if (empty($articleToQty)) {
        return [];
    }

    if (!defined('CHIP_API_TOKEN')) {
        error_log('CHIP_API_TOKEN не определён');
        return [];
    }

    $token = CHIP_API_TOKEN;
    $mh = curl_multi_init();
    $channels = [];
    $requests = [];

    foreach ($articleToQty as $article => $qty) {
        // Валидация артикула
        if (!_validate_article($article)) {
            continue;
        }

        $url = add_query_arg([
            'input' => $article,
            'qty' => $qty,
            'token' => $token
        ], 'https://api.client-service.getchips.ru/client/api/gh/v1/search/partnumber');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_TIMEOUT => 50,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        curl_multi_add_handle($mh, $ch);
        $channels[] = $ch;
        $requests[] = [
            'article' => $article,
            'qty' => $qty,
            'url' => $url,
            'start_time' => microtime(true),
            'ip' => $client_ip
        ];
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 0.1);
        }
    } while ($running > 0);

    $allProducts = [];

    foreach ($channels as $index => $ch) {
        $response = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        $req = $requests[$index];
        _log_api_request(
            $req['article'],
            $req['qty'],
            $req['url'],
            $req['ip'],
            $req['start_time'],
            $httpCode,
            $response
        );

        if ($httpCode !== 200 || !$response) {
            continue;
        }

        $data = json_decode($response, true);
        if (!isset($data['data']) || !is_array($data['data'])) {
            continue;
        }

        $original_article = $req['article'];
        $desired_qty = $req['qty'];

        foreach ($data['data'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity < $desired_qty) {
                continue;
            }

            $orderdays = $item['orderdays'] ?? null;
            $multisite = !empty($item['priceBreak']);
            $price = $multisite ? $item['priceBreak'] : ($item['price'] ?? 0);

            $term = ($orderdays !== null && is_numeric($orderdays))
                ? ((int)$orderdays . ' дн.')
                : ($quantity > 0 ? 'В наличии' : 'Под заказ');

            $allProducts[] = [
                'article' => sanitize_text_field($item['title'] ?? $original_article),
                'name' => sanitize_text_field($item['title'] ?? $original_article),
                'brand' => sanitize_text_field($item['brand'] ?? '-'),
                'available' => $quantity,
                'minq' => (int) ($item['minq'] ?? 1),
                'price' => $price,
                'term' => sanitize_text_field($term),
                'multisite' => $multisite,
                'sPack' => sanitize_text_field($item['sPack'] ?? '-'),
                'folddivision' => sanitize_text_field($item['folddivision'] ?? '-'),
                'donor' => sanitize_text_field($item['donor'] ?? '-'),
                'orderdays' => $orderdays !== null ? (int)$orderdays : null,
                'desired_quantity' => $desired_qty,
            ];
        }
    }

    curl_multi_close($mh);
    return $allProducts;
}

/**
 * Валидация артикула (защита от инъекций)
 */
function _validate_article($article) {
    $article = trim($article);
    if (empty($article)) {
        return false;
    }
    // Разрешаем буквы, цифры, дефис, подчёркивание, точку, слэш
    return preg_match('/^[a-zA-Z0-9\-_\.\/]+$/', $article) === 1;
}

/**
 * Парсит входные данные в массив [артикул => количество]
 */
function _parse_input_to_articles($input) {
    $articleToQty = [];

    // Обработка GET параметров
    if (isset($_GET['article']) && isset($_GET['qty']) && !empty($_GET['article']) && !empty($_GET['qty'])) {
        $articles = $_GET['article'];
        $quantities = $_GET['qty'];

        if (!is_array($articles)) $articles = [$articles];
        if (!is_array($quantities)) $quantities = [$quantities];

        $minLen = min(count($articles), count($quantities));
        if ($minLen === 0) {
            return [];
        }

        for ($i = 0; $i < $minLen; $i++) {
            $article = trim(sanitize_text_field($articles[$i]));
            $qty = (int) sanitize_text_field($quantities[$i]);
            if ($article !== '' && $qty > 0 && _validate_article($article)) {
                $articleToQty[$article] = $qty;
            }
        }

        return $articleToQty;
    }

    // Обработка строки
    if (is_string($input)) {
        $input_clean = trim(str_replace(["\r\n", "\r"], "\n", $input));
        if ($input_clean === '') {
            return [];
        }

        $lines = explode("\n", $input_clean);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (strpos($line, ':') !== false) {
                [$article, $qty_str] = array_map('trim', explode(':', $line, 2));
                $qty = (int) $qty_str;
                if ($qty <= 0) $qty = 1;
            } else {
                $article = $line;
                $qty = 1;
            }

            if ($article !== '' && _validate_article($article)) {
                $articleToQty[$article] = $qty;
            }
        }

        return $articleToQty;
    }

    // Обработка массива
    if (is_array($input)) {
        if (empty($input)) {
            return [];
        }

        if (array_keys($input) !== range(0, count($input) - 1)) {
            $articleToQty = array_filter($input, function($q) {
                return is_numeric($q) && $q > 0;
            });
            $articleToQty = array_map('intval', $articleToQty);
        } else {
            $articleList = array_filter(array_map('trim', $input));
            $articleToQty = array_fill_keys($articleList, 1);
        }

        $articleToQty = array_filter($articleToQty, function($qty, $art) {
            return !empty(trim($art)) && $qty > 0 && _validate_article($art);
        }, ARRAY_FILTER_USE_BOTH);

        return $articleToQty;
    }

    return [];
}

/**
 * Получает IP клиента
 */
function _get_client_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Логирование API-запросов
 */
function _log_api_request($article, $qty, $url, $ip, $start_time, $http_code, $response_body = '') {
    $duration_ms = round((microtime(true) - $start_time) * 1000, 2);
    $timestamp = date('Y-m-d H:i:s');
    $is_error = ($http_code !== 200 || $http_code === 0 || empty($response_body));
    $error_response = $is_error ? ($response_body ?: '[No response]') : '';

    $log_message = sprintf(
        "[API REQUEST LOG]
Time: %s
Client IP: %s
Article: %s | Qty: %d
URL: %s
HTTP Status: %d
Duration: %.2f ms
%s
---
",
        $timestamp,
        $ip,
        $article,
        $qty,
        $url,
        $http_code,
        $duration_ms,
        $error_response ? "Error Response:\n" . wordwrap($error_response, 120) : "Response OK"
    );

    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/chips-api.log';
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}
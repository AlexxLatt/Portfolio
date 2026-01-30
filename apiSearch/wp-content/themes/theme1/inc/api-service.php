<?php

/**
 * Получает данные о товарах из внешнего API по списку артикулов.
 *
 * @param string|array $input — строка (артикул или список через \n) или массив [артикул => qty]
 * @return array Массив товаров
 */



if (!defined('ABSPATH')) {
    exit; // Запрет прямого доступа
}

/**
 * Подключает необходимые скрипты и стили для поиска.
 */
add_action('wp_enqueue_scripts', 'product_search_enqueue_scripts', 100);
function product_search_enqueue_scripts() {
    if (!wp_script_is('jquery', 'enqueued')) {
        wp_enqueue_script('jquery');
    }
    wp_enqueue_style('dashicons');
}


function get_products_data($input) {
    // === IP клиента ===
    $client_ip = !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
        ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])
        : ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    // === Логирование API-запроса в ОДИН файл ===
    $log_api_request = function(
        string $article,
        int $qty,
        string $url,
        string $ip,
        float $start_time,
        int $http_code,
        string $response_body = ''
    ) {
        $duration_ms = round((microtime(true) - $start_time) * 1000, 2);
        $timestamp = date('Y-m-d H:i:s');
        $is_error = ($http_code !== 200 || $http_code === 0 || empty($response_body));
        $error_response = $is_error ? ($response_body ?: '[No response]') : '';

        $log_message = sprintf(
            "[API REQUEST LOG]\n" .
            "Time: %s\n" .
            "Client IP: %s\n" .
            "Article: %s | Qty: %d\n" .
            "URL: %s\n" .
            "HTTP Status: %d\n" .
            "Duration: %.2f ms\n" .
            "%s\n" .
            "---\n",
            $timestamp,
            $ip,
            $article,
            $qty,
            $url,
            $http_code,
            $duration_ms,
            $error_response ? "Error Response:\n" . wordwrap($error_response, 120) : "Response OK"
        );

        // 🔥 ОДИН ФАЙЛ ДЛЯ ВСЕХ ЗАПРОСОВ
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/chips-api.log'; // ← без даты!
        file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
    };

    // === ... (весь остальной код парсинга без изменений) ... ===
    $articleToQty = [];

    if (
        isset($_GET['article']) && isset($_GET['qty']) &&
        !empty($_GET['article']) && !empty($_GET['qty'])
    ) {
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
            if ($article !== '' && $qty > 0) {
                $articleToQty[$article] = $qty;
            }
        }
    } else {
        if (is_string($input)) {
            $input_clean = trim(str_replace(["\r\n", "\r"], "\n", $input));
            if ($input_clean === '') return [];

            $lines = explode("\n", $input_clean);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;

                if (strpos($line, ':') !== false) {
                    [$article, $qty_str] = array_map('trim', explode(':', $line, 2));
                    $qty = (int) $qty_str;
                    if ($qty <= 0) $qty = 1;
                } else {
                    $article = $line;
                    $qty = 1;
                }

                if ($article !== '') {
                    $articleToQty[$article] = $qty;
                }
            }
        } elseif (is_array($input)) {
            if (empty($input)) return [];

            if (array_keys($input) !== range(0, count($input) - 1)) {
                $articleToQty = array_filter($input, fn($q) => is_numeric($q) && $q > 0);
                $articleToQty = array_map('intval', $articleToQty);
            } else {
                $articleList = array_filter(array_map('trim', $input));
                $articleToQty = array_fill_keys($articleList, 1);
            }
        } else {
            return [];
        }

        $articleToQty = array_filter($articleToQty, fn($qty, $art) => !empty(trim($art)) && $qty > 0, ARRAY_FILTER_USE_BOTH);
    }

    if (empty($articleToQty)) return [];

    if (!defined('CHIP_API_TOKEN')) {
        error_log('CHIP_API_TOKEN не определён');
        return [];
    }
    $token = CHIP_API_TOKEN;

    // === Параллельные запросы ===
    $mh = curl_multi_init();
    $channels = [];
    $requests = [];

    foreach ($articleToQty as $article => $qty) {
        // ✅ ИСПРАВЛЕНО: УБРАНЫ ПРОБЕЛЫ ПОСЛЕ "?"
        $url = 'https://api.client-service.getchips.ru/client/api/gh/v1/search/partnumber?' . http_build_query([
            'input' => $article,
            'qty'   => $qty,
            'token' => $token
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

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
        if ($running) curl_multi_select($mh, 0.1);
    } while ($running > 0);

    $allProducts = [];
    foreach ($channels as $index => $ch) {
        $response = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        $req = $requests[$index];
        $log_api_request(
            $req['article'],
            $req['qty'],
            $req['url'],
            $req['ip'],
            $req['start_time'],
            $httpCode,
            $response
        );

        if ($httpCode !== 200 || !$response) continue;

        $data = json_decode($response, true);
        if (!isset($data['data']) || !is_array($data['data'])) continue;

        $original_article = $req['article'];
        $desired_qty = $req['qty'];

        foreach ($data['data'] as $item) {
            if (!is_array($item)) continue;

            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity < $desired_qty) continue;

            $orderdays = $item['orderdays'] ?? null;
            $multisite = !empty($item['priceBreak']);
            $price = $multisite ? $item['priceBreak'] : ($item['price'] ?? 0);

            $term = ($orderdays !== null && is_numeric($orderdays))
                ? ((int)$orderdays . ' дн.')
                : ($quantity > 0 ? 'В наличии' : 'Под заказ');

            $allProducts[] = [
                'article'          => $item['title'] ?? $original_article,
                'name'             => $item['title'] ?? $original_article,
                'brand'            => $item['brand'] ?? '-',
                'available'        => $quantity,
                'minq'             => (int) ($item['minq'] ?? 1),
                'price'            => $price,
                'term'             => $term,
                'multisite'        => $multisite,
                'sPack'            => $item['sPack'] ?? '-',
                'folddivision'     => $item['folddivision'] ?? '-',
                'donor'            => $item['donor'] ?? '-',
                'orderdays'        => $orderdays !== null ? (int)$orderdays : null,
                'desired_quantity' => $desired_qty,
            ];
        }
    }

    curl_multi_close($mh);
    return $allProducts;
}
/**
 * Обёртка для совместимости 
 */
function search_products_by_articles($articles) {
    return get_products_data($articles);
}

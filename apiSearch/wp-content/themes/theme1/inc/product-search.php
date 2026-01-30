<?php
//НАЦЕНКА =============================================================================
// Добавляем страницу настроек

if (!function_exists('debug_log_to_file')) {
    function debug_log_to_file($message, $label = '') {
        $log_file = ABSPATH . 'search_debug.log'; // файл в корне сайта
        $timestamp = date('Y-m-d H:i:s');
        $entry = "[{$timestamp}]";
        if ($label) {
            $entry .= " [{$label}]";
        }
        $entry .= " " . (is_string($message) ? $message : print_r($message, true)) . "\n";
        file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
    }
}
add_action('admin_menu', 'currency_settings_menu');
function currency_settings_menu() {
    add_options_page(
        'Наценка',
        'Наценка',
        'manage_options',
        'currency-settings',
        'currency_settings_page'
    );
}

// Сохранение настроек
add_action('admin_init', 'currency_settings_register');
function currency_settings_register() {
    register_setting('currency_settings_group', 'currency_target');
    register_setting('currency_settings_group', 'currency_markup_type'); // 'percent' или 'fixed'
    register_setting('currency_settings_group', 'currency_markup_value'); // число
    register_setting('currency_settings_group', 'currency_markup_scope'); 
    register_setting('currency_settings_group', 'currency_last_modified', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field'
    ]);
}

// Страница настроек
function currency_settings_page() {
    ?>
    <div class="wrap">
        <h1>Курс валют и наценка</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('currency_settings_group');
            do_settings_sections('currency-settings');
            ?>
            <table class="form-table">
                <tr>
                    <th>Валюта для пересчёта</th>
                    <td>
                        <select name="currency_target">
                            <option value="USD" <?php selected(get_option('currency_target'), 'USD'); ?>>USD (Доллар США)</option>
                            <option value="EUR" <?php selected(get_option('currency_target'), 'EUR'); ?>>EUR (Евро)</option>
                            <option value="CNY" <?php selected(get_option('currency_target'), 'CNY'); ?>>CNY (Юань)</option>
                            <option value="GBP" <?php selected(get_option('currency_target'), 'GBP'); ?>>GBP (Фунт стерлингов)</option>
                            <!-- добавь другие при необходимости -->
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Тип наценки</th>
                    <td>
                        <label><input type="radio" name="currency_markup_type" value="percent" <?php checked(get_option('currency_markup_type'), 'percent'); ?>> Проценты (%)</label><br>
                        <label><input type="radio" name="currency_markup_type" value="fixed" <?php checked(get_option('currency_markup_type'), 'fixed'); ?>> Фиксированная (руб.)</label>
                    </td>
                </tr>
                <tr>
                    <th>Значение наценки</th>
                    <td>
                        <input type="number" step="0.01" name="currency_markup_value" value="<?php echo esc_attr(get_option('currency_markup_value', 0)); ?>" min="0">
                        <p class="description">
                            Например: 5 → +5% или +5 руб., в зависимости от выбранного типа.
                        </p>
                    </td>
                </tr>
                <tr>
                <tr>
                <th>Где применять наценку</th>
                    <td>
                        <label><input type="radio" name="currency_markup_scope" value="rate" <?php checked(get_option('currency_markup_scope', 'rate'), 'rate'); ?>> К курсу валюты</label><br>
                        <label><input type="radio" name="currency_markup_scope" value="price" <?php checked(get_option('currency_markup_scope'), 'price'); ?>> К цене товара</label><br>
                        <label><input type="radio" name="currency_markup_scope" value="both" <?php checked(get_option('currency_markup_scope'), 'both'); ?>> К курсу и к цене</label>
                        <p class="description">
                            • «К курсу» — наценка добавляется к курсу, потом пересчёт.<br>
                            • «К цене» — курс без наценки, наценка добавляется к итоговой цене.<br>
                            • «К курсу и к цене» — применяется дважды (редко нужно).
                        </p>
                    </td>
                </tr>
            </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
/**
 * Получает все настройки валюты и наценки в виде массива, включая кэшированный курс
 * 
 * @return array Массив с настройками и курсом валюты
 */
function get_currency_settings() {
    // Получаем базовые настройки
    $target_currency = get_option('currency_target', 'USD');
    $markup_type = get_option('currency_markup_type', 'percent');
    $markup_value = (float)get_option('currency_markup_value', 0);
    $markup_scope = get_option('currency_markup_scope', 'rate');
    
    // Создаем уникальный ключ для кэширования на основе выбранной валюты
    $transient_key = 'currency_rate_' . $target_currency;
    
    // Пытаемся получить кэшированный курс
    $rate = get_transient($transient_key);
    
    // Если кэша нет, запрашиваем актуальные курсы
    if (false === $rate) {
        $url = 'https://www.cbr.ru/scripts/XML_daily.asp';
        $response = wp_remote_get($url, ['timeout' => 15]);
        
        // Проверяем наличие ошибок
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $xml_data = wp_remote_retrieve_body($response);
            if (!empty($xml_data)) {
                $xml = simplexml_load_string($xml_data);
                if ($xml) {
                    // Ищем нужную валюту
                    foreach ($xml->Valute as $valute) {
                        if ((string)$valute->CharCode === $target_currency) {
                            $nominal = (int)$valute->Nominal;
                            $value = (float)str_replace(',', '.', (string)$valute->Value);
                            $rate = $value / $nominal;
                            // Кэшируем курс на 24 часа
                            set_transient($transient_key, $rate, DAY_IN_SECONDS);
                            break;
                        }
                    }
                }
            }
        }
        
        // Если не удалось получить курс, используем значение по умолчанию или null
        if (false === $rate) {
            $rate = null;
        }
    }
    
    // Возвращаем массив со всеми настройками и курсом
    return [
        'target' => $target_currency,           // Выбранная валюта
        'markup_type' => $markup_type,          // Тип наценки: 'percent' или 'fixed'
        'markup_value' => $markup_value,        // Значение наценки
        'markup_scope' => $markup_scope,        // Где применяется: 'rate', 'price' или 'both'
        'rate' => $rate                         // Курс валюты к рублю (может быть null)
    ];
    
}




//НАЦЕНКА ============================================================================= (конец)

/**
 * Файл: inc/product-search.php
 * Назначение: Поиск товаров по артикулу через API, генерация HTML-таблицы,
 * AJAX-обработчики и шорткод для фронтенда.
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

/**
 * Получает данные о товарах из внешнего API по списку артикулов.
 *
 * @param string|array $input — строка (артикул или список через \n) или массив [артикул => qty]
 * @return array Массив товаров
 */
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

        // ОДИН ФАЙЛ ДЛЯ ВСЕХ ЗАПРОСОВ
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
 * Обёртка для совместимости (можно удалить, если не используется).
 */
function search_products_by_articles($articles) {
    return get_products_data($articles);
}

/**
 * Генерирует HTML-таблицу товаров для отображения на фронтенде.
 *
 * @param array $products — массив товаров от get_products_data()
 * @param array|null $original_articles — исходные артикулы из файла/списка
 * @return string HTML
 */
function generate_products_table($products, $original_articles = null) {
    if (empty($products)) {
        return '
        <div class="component-request-form">
            <h2 class="form-title">К сожалению, мы не нашли товар, но мы можем<br> проработать запрос в ручную</h2>
            <form method="POST" action="/quote-handler.php" class="order-form">
                <input type="hidden" name="action" value="submit_component_request">
                
                <div class="order-form__input-wrapper">
                    <p class="order-form__field first searchForm">
                        <label class="order-form__label required-input">Артикул </label>
                        <input class="order-form__input" type="text" name="title" required placeholder="C1005X6S1">
                    </p>
                    <p class="order-form__field first searchForm">
                        <label class="order-form__label required-input brend">Бренд </label>
                        <input class="order-form__input" type="text" name="brand" required placeholder="Бренд">
                    </p>
                </div>
                <div class="order-form__input-wrapper">
                    <p class="order-form__field searchForm">
                        <label class="order-form__label required-input">Количество </label>
                        <input class="order-form__input" type="number" name="quantity" placeholder="1" required min="1">
                    </p>
                    <p class="order-form__field searchForm">
                        <label class="order-form__label">Цена</label>
                        <input class="order-form__input" type="number" name="price" min="1"  placeholder="1000">
                    </p>
                </div>
                <div class="order-form__input-wrapper">
                    <p class="order-form__field searchForm">
                        <label class="order-form__label required-input">Срок поставки </label>
                        <select class="order-form__input option" name="delivery_time" required>
                            <option value="">Выберите срок поставки</option>
                            <option value="up_to_5_weeks">не более 5 недель</option>
                            <option value="more_than_5_weeks">более 5 недель</option>
                        </select>
                    </p>    
                
                    <p class="order-form__field searchForm">
                        <label class="order-form__label required-input">Компания </label>
                        <input class="order-form__input" type="text" placeholder="1" name="company" required>
                    </p>
                </div>
                <p class="order-form__field searchForm">
                    <label class="order-form__label textarea">Ваш комментарий <br>
                        <textarea class="order-form__textarea"  name="comment" rows="4" placeholder="Ваш комментарий"></textarea>
                    </label>
                </p>
                <p class="order-form__submit">
                    <button type="submit" id="quoteSubmitBtn" class="order-form__btn btn">Запросить квоту</button>
                </p>
            </form>
        </div>
        ';
    }

    // Получаем настройки из админки
    $currency_settings = get_currency_settings();
    
    // === Выбираем артикулы для ссылки ===
    if ($original_articles !== null) {
        $articles_for_link = $original_articles;
    } else {
        $articles_for_link = [];
        foreach ($products as $product) {
            $article = $product['article'] ?? '';
            if ($article === '') continue;
            if (isset($articles_for_link[$article])) continue;
            $qty = (int)($product['desired_quantity'] ?? ($product['minq'] ?? 1));
            $articles_for_link[$article] = $qty;
        }
    }
    
    // Формируем URL
    $exportParams = [];
    foreach ($articles_for_link as $article => $qty) {
        $exportParams[] = 'article[]=' . urlencode($article);
        $exportParams[] = 'qty[]=' . urlencode((string)$qty);
    }
    $queryString = implode('&', $exportParams);
    $get_link = home_url('/') . '?' . $queryString;
    
    $rate = $currency_settings['rate'] ?? 1;
    $markup_type = $currency_settings['markup_type'] ?? 'percent';
    $markup_value = $currency_settings['markup_value'] ?? 0;
    $markup_scope = $currency_settings['markup_scope'] ?? 'rate';
    $download_icon = get_stylesheet_directory_uri() . '/images/download.png';
    $link_ikon = get_stylesheet_directory_uri() . '/images/ci_link.png';
   
    $html = '
       
    
       <div class="header-table-wrapper">
        <span class="table-title">Результаты поиска</span>
        <a data-link="' . esc_attr($get_link) . '"
           data-bs-toggle="tooltip" 
           data-bs-placement="top"
           class="copy-url-link"
           title="Скопировать URL">
            <img class="link-table" src="' . esc_url($link_ikon) . '" alt="Скопировать URL">
        </a>
        <img src="' . esc_url($download_icon) . '" 
             id="exportToExcel" 
             class="btn-primary download-table" 
             data-bs-toggle="tooltip" 
             data-bs-placement="top" 
             title="Скачать таблицу в Excel"
             alt="Скачать Excel">
    </div>
    <div class="table-responsive mt-4 overflow">
        <table class="product-search-table table-striped table-hover align-middle mb-0">
           <thead class="table-light">
                <tr>
                    <th scope="col">Поставщик</th>
                    <th scope="col">Наименование</th>
                    <th scope="col">Бренд</th>
                    <th scope="col">Доступно</th>
                    <th scope="col" class="sortable" data-sort="term">Срок
                     <span class="sort-arrows ms-1" id="filter2">
                            <span class="arrow-up">⌃</span>
                            <span class="arrow-down">⌄</span>
                     </span>
                    </th>
                    <th scope="col" class="sortable" data-sort="price_rub">Цена, руб.
                        <span class="sort-arrows ms-1" id="filter2">
                            <span class="arrow-up">⌃</span>
                            <span class="arrow-down">⌄</span>
                        </span>
                    </th>
                    <th scope="col">Кол-во</th>
                    <th scope="col" class="sortable" data-sort="total_rub">Сумма, руб.
                        <span class="sort-arrows ms-1" id="filter3">
                            <span class="arrow-up">⌃</span>
                            <span class="arrow-down">⌄</span>
                        </span>
                    </th>
                    <th scope="col">В корзину</th>
                </tr>
            </thead>
            <tbody>';

    // === СОРТИРОВКА: сначала товары, подходящие по кратности ===
    $sorted_products = [];
    $matching = [];
    $non_matching = [];

    foreach ($products as $product) {
        $desired_qty = (int)($product['desired_quantity'] ?? 1);
        $folddivision = (int)($product['folddivision'] ?? 1);
        if ($folddivision <= 0) $folddivision = 1;

        if ($desired_qty % $folddivision === 0) {
            $matching[] = $product;
        } else {
            $non_matching[] = $product;
        }
    }
    $sorted_products = array_merge($matching, $non_matching);

    // === Генерация строк таблицы ===
    foreach ($sorted_products as $product) {
        $name = $product['name'] ?? '—';
        $donor = $product['donor'] ?? '—';
        $orderdays = $product['orderdays'] ?? '—';
        $brand = $product['brand'] ?? '—';
        $available = intval($product['available'] ?? 0);
        $min_order = intval($product['minq'] ?? 1);
        $term = $product['term'] ?? '—';
        $multisite = $product['multisite'] ?? false;
        $price = $product['price'];
        $sPack = $product['sPack'];
        $folddivision_val = $product['folddivision'];
        $desired_qty = $product['desired_quantity'] ?? $min_order;

        // Определяем значение для input "Кол-во"
        $quantity_input = 0;
        if ($available >= $min_order) {
            $quantity_input = min($desired_qty, $available);
            if ($quantity_input < $min_order) {
                $quantity_input = $min_order;
            }
        }

        // === РАБОТА С КУРСОМ И НАЦЕНКАМИ ===
        $final_rate = $rate;
        if ($markup_scope === 'rate' || $markup_scope === 'both') {
            if ($markup_type === 'percent') {
                $final_rate = $rate * (1 + $markup_value / 100);
            } else {
                $final_rate = $rate + $markup_value;
            }
            $final_rate = round($final_rate, 4);
        }

        // === Определяем цену в USD ===
        if ($multisite && is_array($price) && !empty($price)) {
            $first = reset($price);
            $price_usd = floatval($first['price'] ?? 0);
        } else {
            $price_usd = floatval($price);
        }

        $price_rub = round($price_usd * $final_rate, 2);
        if ($markup_scope === 'price' || $markup_scope === 'both') {
            if ($markup_type === 'percent') {
                $price_rub = $price_rub * (1 + $markup_value / 100);
            } else {
                $price_rub = $price_rub + $markup_value;
            }
            $price_rub = round($price_rub, 2);
        }

        $disabled = ($available < $min_order) ? 'disabled' : '';
        $total_rub = round($price_rub * $quantity_input, 2);

        // Генерация блока цен
        $priceHtml = '';
        if ($multisite && is_array($price)) {
            usort($price, function($a, $b) {
                return intval($a['quantity'] ?? 0) <=> intval($b['quantity'] ?? 0);
            });
        
            $priceHtml = '<div class="multiPrice">';
            foreach ($price as $pb) {
                $pb_price_usd = floatval($pb['price'] ?? 0);
                $pb_price_rub = round($pb_price_usd * $final_rate, 2);
                
                if ($markup_scope === 'price' || $markup_scope === 'both') {
                    if ($markup_type === 'percent') {
                        $pb_price_rub = $pb_price_rub * (1 + $markup_value / 100);
                    } else {
                        $pb_price_rub = $pb_price_rub + $markup_value;
                    }
                    $pb_price_rub = round($pb_price_rub, 2);
                }
                
                $priceHtml .= "<div class='multiPrice__item'>
                    <span data-multi='quantity' value='" . esc_attr($pb["quantity"] ?? '') . "'>" . esc_html($pb["quantity"] ?? '') . "</span> x 
                    <span data-multi='price' value='" . $pb_price_rub . "'>" . number_format($pb_price_rub, 2, ',', ' ') . " руб.</span>
                </div>";
            }
            $priceHtml .= '</div>';
        } else {
            $priceHtml = number_format($price_rub, 2, ',', ' ') . ' руб.';
        }
        
        $basket_icon = get_stylesheet_directory_uri() . '/images/akar-icons_basket.png';

        $html .= sprintf(
            '<tr class="column_head">
                <td class="donor" data-label="Поставщик">%1$s</td>
                <td class="product-name" data-label="Наименование">%2$s</td>
                <td class="brand" data-label="Бренд">%3$s</td>
                <td class="available" data-label="Доступно">%4$s шт</td>
                <td class="term" data-value="%18$s" data-label="Срок">%18$s</td>
                <td class="price-rub" data-value="%6$F" data-label="Цена">%7$s</td>
                <td class="quantity" data-label="Кол-во">
                    <input type="number" class="quantity-input form-control form-control-sm"
                        value="%8$d"
                        min="%9$d"
                        max="%10$d"
                        %11$s
                        style="width: 70px !important;"
                    >
                    <div class="minq descText">Минимум: %9$d</div>
                    <div class="folddivision descText">Кратность: %12$s</div>
                    <div class="sPack descText">Норма уп: %13$s</div>
                </td>
                <td class="total-rub" data-value="%14$F" data-label="Сумма">%15$s руб.</td>
                <td data-label="В корзину">
                    <button class="add-to-cart-btn btn btn-sm btn-success custom" 
                        data-article="%16$s"
                        data-name="%2$s"
                        data-brand="%3$s"  
                        data-price="%6$F"
                        data-minq="%9$d"
                        data-available="%10$d"
                        data-donor="%1$s"
                        data-term="%18$s"
                        %11$s>
                        <img class="imgBasket" src="%17$s" alt="Корзина">
                    </button>
                </td>
            </tr>',
            esc_html($donor),                           // %1$s
            esc_html($name),                            // %2$s
            esc_html($brand),                           // %3$s
            esc_html($available),                       // %4$s
            esc_html($orderdays),                       // %5$s
            $price_rub,                                 // %6$F
            $priceHtml,                                 // %7$s
            (int) $quantity_input,                      // %8$d ← ВВЕДЁННОЕ КОЛИЧЕСТВО
            (int) $min_order,                           // %9$d
            (int) $available,                           // %10$d
            $disabled,                                  // %11$s
            esc_html($folddivision_val),                // %12$s
            esc_html($sPack),                           // %13$s
            $total_rub,                                 // %14$F
            number_format($total_rub, 2, ',', ' '),     // %15$s
            esc_html($product['article'] ?? $name),     // %16$s
            esc_url($basket_icon),                      // %17$s
            esc_html($term)                             // %18$s
        );
    }

    $html .= '</tbody></table></div>';
    return $html;
}
// === AJAX-обработчики ===

add_action('wp_ajax_nopriv_product_search_by_article', 'handle_product_search_by_article');
add_action('wp_ajax_product_search_by_article', 'handle_product_search_by_article');

function handle_product_search_by_article() {

    $input = sanitize_textarea_field($_POST['article'] ?? '');
    if (empty(trim($input))) {
        wp_send_json_error('Пустой запрос');
    }

    $original_articles = [];
    $input_clean = trim(str_replace(["\r\n", "\r"], "\n", $input));
    if ($input_clean !== '') {
        $lines = explode("\n", $input_clean);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (strpos($line, ':') !== false) {
                $parts = explode(':', $line, 2);
                $article = trim($parts[0]);
                $qty = max(1, (int) trim($parts[1]));
            } else {
                $article = $line;
                $qty = 1;
            }
            if ($article !== '') {
                $original_articles[$article] = $qty;
            }
        }
    }

    $products = get_products_data($input);
    $html = generate_products_table($products, $original_articles); // ← передаём оригинальный ввод
    wp_send_json_success($html);
}
add_action('wp_ajax_nopriv_product_search_by_list', 'handle_product_search_by_list');
add_action('wp_ajax_product_search_by_list', 'handle_product_search_by_list');
function handle_product_search_by_list() {
    check_ajax_referer('product_search_nonce', 'security');
    
    // Получаем СТРОКУ из textarea
    $articles_input = isset($_POST['articles']) ? sanitize_textarea_field($_POST['articles']) : '';
    
    if (empty(trim($articles_input))) {
        wp_send_json_error('Список артикулов пуст');
    }

    // Передаём СТРОКУ напрямую в get_products_data()
    $original_articles = [];
    $input_clean = trim(str_replace(["\r\n", "\r"], "\n", $articles_input));
    if ($input_clean !== '') {
        $lines = explode("\n", $input_clean);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (strpos($line, ':') !== false) {
                $parts = explode(':', $line, 2);
                $article = trim($parts[0]);
                $qty = max(1, (int) trim($parts[1]));
            } else {
                $article = $line;
                $qty = 1;
            }
            if ($article !== '') {
                $original_articles[$article] = $qty;
            }
        }
    }
    
    $products = get_products_data($articles_input);
    $html = generate_products_table($products, $original_articles);
    
    wp_send_json_success($html);
}



use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

add_action('wp_ajax_nopriv_product_search_by_file', 'handle_product_search_by_file');
add_action('wp_ajax_product_search_by_file', 'handle_product_search_by_file');
function handle_product_search_by_file() {
    check_ajax_referer('product_search_nonce', 'security');

    if (!isset($_FILES['file']) || empty($_FILES['file']['tmp_name'])) {
        wp_send_json_error('Файл не загружен');
    }

    $file = $_FILES['file'];
    $file_path = $file['tmp_name'];
    $file_name = $file['name'];
    $articles = [];

    // Определяем расширение
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    try {
        if ($ext === 'csv') {
            // Обработка CSV вручную (как раньше)
            $content = file_get_contents($file_path);
            $content = str_replace(["\r\n", "\r"], "\n", $content);
            $rows = str_getcsv($content, "\n");
            foreach ($rows as $row) {
                $row = trim($row);
                if ($row === '') continue;
                $cols = str_getcsv($row, ',');
                $article = !empty($cols[0]) ? trim($cols[0]) : null;
                $qty = !empty($cols[1]) ? (int) trim($cols[1]) : 1;
                if ($article && $qty > 0) {
                    $articles[$article] = $qty;
                }
            }
        } else {
            // Обработка .xls, .xlsx через PhpSpreadsheet
            $reader = IOFactory::createReaderForFile($file_path);
            $spreadsheet = $reader->load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray(null, true, true, true); // ['A' => ..., 'B' => ...]

            foreach ($rows as $row) {
                $article = !empty($row['A']) ? trim($row['A']) : null;
                $qty = !empty($row['B']) ? (int) $row['B'] : 1;
                if ($article && $qty > 0) {
                    $articles[$article] = $qty;
                }
            }
        }
    } catch (ReaderException $e) {
        wp_send_json_error('Ошибка чтения файла: некорректный формат Excel.');
    } catch (Exception $e) {
        wp_send_json_error('Ошибка обработки файла: ' . $e->getMessage());
    }

    if (empty($articles)) {
        wp_send_json_error('Не найдено корректных артикулов в файле. Убедитесь, что данные в 1-м и 2-м столбцах.');
    }

    $products = get_products_data($articles);
    $html = generate_products_table($products, $articles); // ← передаём исходные артикулы
    wp_send_json_success($html);
}


// === Шорткод ===

add_shortcode('product_search', 'product_search_shortcode');
function product_search_shortcode() {
    
    $search = get_stylesheet_directory_uri() . '/images/search.png';
    $searchList = get_stylesheet_directory_uri() . '/images/search-list.png';
    
    ob_start();
    ?>
    
    <div class="product-search-container">
        <div class="section__header">
            <h2 class="section__title section__title--center">Глобальный поиск электронных компонентов</h2>
        </div>
        <div class="modal-shadow" data-shadow data-modal-close></div>
        <section class="search">
          <span class="search-help">
            <span class="search-help__label">Как пользоваться поиском</span>
            <span class="search-help__icon" aria-label="Показать подсказку по поиску" tabindex="0">ⓘ</span>
            <div class="search-help__tooltip" role="tooltip">
                <div class="search-help__step">
                    <img src="<?php echo esc_url($search); ?>" alt="Поиск по артикулу" class="search-help__image">
                    <p>Для поиска укажите <strong>Артикул </strong>и<strong> Количество</strong></p>
                </div>
                <div class="search-help__step">
                    <img src="<?php echo esc_url($searchList); ?>" alt="Поиск по списку компонентов" class="search-help__image">
                    <p>Для поиска по списку введите через новую строку<br><strong>Артикул</strong> или <strong>Артикул:Количество </strong></p>
                </div>
            </div>
        </span>
            <form class="search__form" id="product-search-form">
                  
              
                <div class="search__input-container">
                     <input type="search" class="search__input single-mode article-search" id="single-search-input" placeholder="Поиск по артикулу" aria-label="Поиск по артикулу">
                     <input type="search" class="search__input single-mode quanity-search" id="single-search-input-quanity" placeholder="Кол-во" aria-label="Кол-во">
                    <textarea class="search__input list-mode disable" id="list-search-input" rows="1" placeholder="Поиск по артикулу"></textarea>
                </div>
                <div class="search__buttons">
                    <button class="search__submit" type="submit">Найти</button>
                    <div class="search__upload" data-upload>
                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="28" viewBox="0 0 26 28" fill="none" aria-hidden="true">
                            <!-- SVG content unchanged -->
                            <path d="M20.1 8.67502L20.6787 8.09502C21.1395 7.63404 21.7646 7.375 22.4164 7.37488C23.0682 7.37476 23.6934 7.63358 24.1543 8.09439C24.6153 8.5552 24.8744 9.18027 24.8745 9.83207C24.8746 10.4839 24.6158 11.109 24.155 11.57L23.5762 12.15M20.1 8.67502C20.1 8.67502 20.1725 9.90502 21.2587 10.9913C22.345 12.0775 23.5762 12.15 23.5762 12.15M20.1 8.67502L14.775 14C14.4125 14.36 14.2325 14.5413 14.0775 14.74C13.8941 14.975 13.7383 15.2275 13.61 15.4975C13.5012 15.725 13.4212 15.9663 13.26 16.45L12.7437 18L12.5762 18.5013M23.5762 12.15L18.2512 17.475C17.8887 17.8375 17.7087 18.0175 17.51 18.1725C17.275 18.3558 17.0225 18.5117 16.7525 18.64C16.525 18.7488 16.2837 18.8288 15.8 18.99L14.25 19.5063L13.7487 19.6738M12.5762 18.5013L12.41 19.0038C12.3712 19.1204 12.3657 19.2455 12.394 19.3651C12.4223 19.4847 12.4834 19.5941 12.5703 19.681C12.6572 19.7679 12.7665 19.8289 12.8861 19.8572C13.0057 19.8855 13.1308 19.88 13.2475 19.8413L13.7487 19.6738M12.5762 18.5013L13.7487 19.6738" stroke="#FF5E14" stroke-width="2.25" />
                            <path d="M7.375 14.875H10.5M7.375 9.875H15.5M7.375 19.875H9.25M1.125 16.125V11.125C1.125 6.41125 1.125 4.05375 2.59 2.59C4.055 1.12625 6.41125 1.125 11.125 1.125H13.625C18.3387 1.125 20.6962 1.125 2.59 2.59M23.625 16.125C23.625 20.8387 23.625 23.1962 22.16 24.66M22.16 24.66C20.6962 26.125 18.3387 26.125 13.625 26.125H11.125C6.41125 26.125 4.05375 26.125 2.59 24.66M22.16 24.66C23.34 23.4812 23.5687 21.725 23.6137 18.625" stroke="#FF5E14" stroke-width="2.25" stroke-linecap="round" />
                        </svg>
                        <span class="search__upload-text">Загрузка файла Excel</span>
                    </div>
                </div>
                <div class="search__mode">
                    <div class="search__mode-item">
                        <label class="search__mode-label" for="search-mode-single">
                            <input class="search__mode-input" type="radio" id="search-mode-single" name="search-mode" checked />
                            <span class="search__mode-input--custom"></span>
                            По одному компоненту
                        </label>
                    </div>
                    <div class="search__mode-item">
                        <label class="search__mode-label" for="search-mode-list">
                            <input class="search__mode-input" type="radio" id="search-mode-list" name="search-mode" />
                            <span class="search__mode-input--custom"></span>
                            По списку компонентов
                        </label>
                    </div>
                </div>
            </form>
        </section>

       
      <div class="modal-upload" data-modal-upload>
            <svg class="modal-upload__close" data-modal-close xmlns="http://www.w3.org/2000/svg" width="23" height="23"
              viewBox="0 0 23 23" fill="none">
              <path
                d="M1.125 1.125L11.125 11.125M21.125 21.125L11.125 11.125M11.125 11.125L21.125 1.125M11.125 11.125L1.125 21.125"
                stroke="#333646" stroke-width="2.25" stroke-linecap="round" />
            </svg>
            <span class="modal-upload__text">Выберите или перетащите сюда Excel файл. Максимальное кол-во строк 100. Внимание!
              Файл должен быть заполнен строго по
              примеру файла,без указания иных символов и пробелов в колонке с количеством</span>
            <div class="modal-upload__field" data-dropzone>
              <input type="file" class="modal-upload__input" id="excel-upload" accept=".xls,.xlsx">
              <label class="modal-upload__label" for="excel-upload">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 15 15" fill="none">
                  <path
                    d="M4.75633 9.47048L9.4705 4.75632M2.988 6.52382L1.80966 7.70215C1.50013 8.01169 1.25459 8.37916 1.08707 8.78359C0.91955 9.18802 0.833328 9.62148 0.833328 10.0592C0.833328 10.497 0.91955 10.9304 1.08707 11.3349C1.25459 11.7393 1.50013 12.1068 1.80966 12.4163C2.1192 12.7259 2.48667 12.9714 2.8911 13.1389C3.29553 13.3064 3.729 13.3927 4.16675 13.3927C4.6045 13.3927 5.03796 13.3064 5.44239 13.1389C5.84682 12.9714 6.21429 12.7259 6.52383 12.4163L7.7005 11.238M6.523 2.98798L7.70133 1.80965C8.01087 1.50011 8.37834 1.25457 8.78277 1.08705C9.1872 0.919534 9.62066 0.833313 10.0584 0.833313C10.4962 0.833313 10.9296 0.919534 11.3341 1.08705C11.7385 1.25457 12.106 1.50011 12.4155 1.80965C12.725 2.11919 12.9706 2.48666 13.1381 2.89109C13.3056 3.29552 13.3918 3.72898 13.3918 4.16673C13.3918 4.60448 13.3056 5.03795 13.1381 5.44238C12.9706 5.84681 12.725 6.21428 12.4155 6.52382L11.2372 7.70215"
                    stroke="#FF5E14" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <span class="modal-upload__filename" data-upload-filename>Выбрать файл</span>
              </label>
            </div>
           <a class="modal-upload__example" href="https://megaatom.com/wp-content/uploads/Книга1.xlsx">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="18" viewBox="0 0 14 18" fill="none">
                <path
                  d="M7.625 1.125V5.45833C7.625 5.67935 7.7128 5.89131 7.86908 6.04759C8.02536 6.20387 8.23732 6.29167 8.45833 6.29167H12.7917"
                  stroke="#FF5E14" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" />
                <path
                  d="M5.29167 8.625L8.625 12.7917M5.29167 12.7917L8.625 8.625M11.125 16.125H2.79167C2.34964 16.125 1.92572 15.9494 1.61316 15.6368C1.30059 15.3243 1.125 14.9004 1.125 14.4583V2.79167C1.125 2.34964 1.30059 1.92572 1.61316 1.61316C1.92572 1.30059 2.34964 1.125 2.79167 1.125H8.625L12.7917 5.29167V14.4583C12.7917 14.9004 12.6161 15.3243 12.3035 15.6368C11.991 15.9494 11.567 16.125 11.125 16.125Z"
                  stroke="#FF5E14" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              Пример загрузки Excel файла
            </a>
            <button class="modal-upload__submit" data-modal-close>Отправить</button>
      </div>

        <div id="product-search-results"></div>
    </div>
    <?php
    return ob_get_clean();
}

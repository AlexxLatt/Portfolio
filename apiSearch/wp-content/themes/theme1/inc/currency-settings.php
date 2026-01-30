<?php
/*
    Этот файл создает страницу настроек для управления наценкой на товары,
    а также функции для получения этих настроек и курса валюты.
    Курс валюты кэшируется на 24 часа для оптимизации производительности.
    Сами курсы валют идут из Api ЦБ РФ.    
*/
if (!defined('ABSPATH')) {
    exit; // Запрет прямого доступа
}
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





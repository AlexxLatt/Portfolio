<?php
/**
 * Файл: inc/currency-settings.php
 * Назначение: Настройки валюты, курса ЦБ РФ и наценок
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==========================================
// СТРАНИЦА НАСТРОЕК В АДМИНКЕ
// ==========================================

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

add_action('admin_init', 'currency_settings_register');
function currency_settings_register() {
    register_setting('currency_settings_group', 'currency_target');
    register_setting('currency_settings_group', 'currency_markup_type');
    register_setting('currency_settings_group', 'currency_markup_value');
    register_setting('currency_settings_group', 'currency_markup_scope');
}

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
                        <p class="description">Например: 5 → +5% или +5 руб.</p>
                    </td>
                </tr>
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
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Получает настройки валюты и кэшированный курс ЦБ РФ
 * @return array
 */
function get_currency_settings() {
    $target_currency = get_option('currency_target', 'USD');
    $markup_type = get_option('currency_markup_type', 'percent');
    $markup_value = (float) get_option('currency_markup_value', 0);
    $markup_scope = get_option('currency_markup_scope', 'rate');

    $transient_key = 'currency_rate_' . sanitize_text_field($target_currency);
    $rate = get_transient($transient_key);

    if (false === $rate) {
        $url = 'https://www.cbr.ru/scripts/XML_daily.asp';
        $response = wp_remote_get($url, ['timeout' => 15]);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $xml_data = wp_remote_retrieve_body($response);
            if (!empty($xml_data)) {
                $xml = simplexml_load_string($xml_data);
                if ($xml) {
                    foreach ($xml->Valute as $valute) {
                        if ((string)$valute->CharCode === $target_currency) {
                            $nominal = (int)$valute->Nominal;
                            $value = (float) str_replace(',', '.', (string)$valute->Value);
                            $rate = $value / $nominal;
                            set_transient($transient_key, $rate, DAY_IN_SECONDS);
                            break;
                        }
                    }
                }
            }
        }

        if (false === $rate) {
            $rate = null;
        }
    }

    return [
        'target' => $target_currency,
        'markup_type' => $markup_type,
        'markup_value' => $markup_value,
        'markup_scope' => $markup_scope,
        'rate' => $rate
    ];
}
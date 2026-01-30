<?php
/**
 * Файл: inc/html-generator.php
 * Назначение: Генерация HTML-таблицы 
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Генерирует безопасную HTML-таблицу товаров
 * @param array $products
 * @param array|null $original_articles
 * @return string
 */
function generate_products_table($products, $original_articles = null) {
    if (empty($products)) {
        return _get_no_results_form();
    }

    $currency_settings = get_currency_settings();

    // Формируем ссылку для копирования
    $articles_for_link = _prepare_articles_for_link($products, $original_articles);
    $get_link = _build_query_link($articles_for_link);

    // Получаем настройки
    $rate = $currency_settings['rate'] ?? 1;
    $markup_type = $currency_settings['markup_type'] ?? 'percent';
    $markup_value = $currency_settings['markup_value'] ?? 0;
    $markup_scope = $currency_settings['markup_scope'] ?? 'rate';

    $download_icon = get_stylesheet_directory_uri() . '/images/download.png';
    $link_icon = get_stylesheet_directory_uri() . '/images/ci_link.png';

    // Начало таблицы
    $html = sprintf('
<div class="header-table-wrapper">
    <span class="table-title">Результаты поиска</span>
    <a data-link="%s"
       data-bs-toggle="tooltip"
       data-bs-placement="top"
       class="copy-url-link"
       title="Скопировать URL">
        <img class="link-table" src="%s" alt="Скопировать URL">
    </a>
    <img src="%s"
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
                    <span class="sort-arrows ms-1">
                        <span class="arrow-up">⌃</span>
                        <span class="arrow-down">⌄</span>
                    </span>
                </th>
                <th scope="col" class="sortable" data-sort="price_rub">Цена, руб.
                    <span class="sort-arrows ms-1">
                        <span class="arrow-up">⌃</span>
                        <span class="arrow-down">⌄</span>
                    </span>
                </th>
                <th scope="col">Кол-во</th>
                <th scope="col" class="sortable" data-sort="total_rub">Сумма, руб.
                    <span class="sort-arrows ms-1">
                        <span class="arrow-up">⌃</span>
                        <span class="arrow-down">⌄</span>
                    </span>
                </th>
                <th scope="col">В корзину</th>
            </tr>
        </thead>
        <tbody>',
        esc_attr($get_link),
        esc_url($link_icon),
        esc_url($download_icon)
    );

    // Сортируем: сначала товары по кратности
    $sorted_products = _sort_products_by_folddivision($products);

    // Генерация строк
    foreach ($sorted_products as $product) {
        $html .= _generate_product_row($product, $rate, $markup_type, $markup_value, $markup_scope);
    }

    $html .= '</tbody></table></div>';
    return $html;
}

/**
 * Сортировка товаров: сначала подходящие по кратности
 */
function _sort_products_by_folddivision($products) {
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

    return array_merge($matching, $non_matching);
}

/**
 * Генерация одной строки таблицы с экранированием
 */
function _generate_product_row($product, $rate, $markup_type, $markup_value, $markup_scope) {
    $name = $product['name'] ?? '—';
    $donor = $product['donor'] ?? '—';
    $brand = $product['brand'] ?? '—';
    $available = intval($product['available'] ?? 0);
    $min_order = intval($product['minq'] ?? 1);
    $term = $product['term'] ?? '—';
    $multisite = $product['multisite'] ?? false;
    $price = $product['price'];
    $sPack = $product['sPack'];
    $folddivision_val = $product['folddivision'];
    $desired_qty = $product['desired_quantity'] ?? $min_order;

    // Определяем количество для input
    $quantity_input = 0;
    if ($available >= $min_order) {
        $quantity_input = min($desired_qty, $available);
        if ($quantity_input < $min_order) {
            $quantity_input = $min_order;
        }
    }

    // Применяем наценку к курсу
    $final_rate = $rate;
    if ($markup_scope === 'rate' || $markup_scope === 'both') {
        if ($markup_type === 'percent') {
            $final_rate = $rate * (1 + $markup_value / 100);
        } else {
            $final_rate = $rate + $markup_value;
        }
        $final_rate = round($final_rate, 4);
    }

    // Определяем цену в USD
    if ($multisite && is_array($price) && !empty($price)) {
        $first = reset($price);
        $price_usd = floatval($first['price'] ?? 0);
    } else {
        $price_usd = floatval($price);
    }

    $price_rub = round($price_usd * $final_rate, 2);

    // Применяем наценку к цене
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
    $priceHtml = _generate_price_html($price, $multisite, $final_rate, $markup_type, $markup_value, $markup_scope);

    $basket_icon = get_stylesheet_directory_uri() . '/images/akar-icons_basket.png';

    // ФОРМИРУЕМ СТРОКУ С ЭКРАНИРОВАНИЕМ ВСЕХ ДАННЫХ
    $row_html = sprintf(
        '<tr class="column_head">
            <td class="donor" data-label="Поставщик">%s</td>
            <td class="product-name" data-label="Наименование">%s</td>
            <td class="brand" data-label="Бренд">%s</td>
            <td class="available" data-label="Доступно">%s шт</td>
            <td class="term" data-value="%s" data-label="Срок">%s</td>
            <td class="price-rub" data-value="%F" data-label="Цена">%s</td>
            <td class="quantity" data-label="Кол-во">
                <input type="number" class="quantity-input form-control form-control-sm"
                       value="%d"
                       min="%d"
                       max="%d"
                       %s
                       style="width: 70px !important;">
                <div class="minq descText">Минимум: %d</div>
                <div class="folddivision descText">Кратность: %s</div>
                <div class="sPack descText">Норма уп: %s</div>
            </td>
            <td class="total-rub" data-value="%F" data-label="Сумма">%s руб.</td>
            <td data-label="В корзину">
                <button class="add-to-cart-btn btn btn-sm btn-success custom"
                        data-article="%s"
                        data-name="%s"
                        data-brand="%s"
                        data-price="%F"
                        data-minq="%d"
                        data-available="%d"
                        data-donor="%s"
                        data-term="%s"
                        %s>
                    <img class="imgBasket" src="%s" alt="Корзина">
                </button>
            </td>
        </tr>',
        esc_html($donor),                           // Поставщик
        esc_html($name),                            // Наименование
        esc_html($brand),                           // Бренд
        esc_html($available),                       // Доступно
        esc_attr($term),                            // data-value для сортировки
        esc_html($term),                            // Срок
        $price_rub,                                 // Цена для сортировки
        $priceHtml,                                 // HTML блок цен
        (int) $quantity_input,                      // Количество
        (int) $min_order,                           // Минимум
        (int) $available,                           // Максимум
        $disabled,                                  // disabled
        (int) $min_order,                           // Минимум (дубль для текста)
        esc_html($folddivision_val),                // Кратность
        esc_html($sPack),                           // Норма упаковки
        $total_rub,                                 // Сумма для сортировки
        number_format($total_rub, 2, ',', ' '),     // Сумма отформатированная
        esc_attr($product['article'] ?? $name),     // data-article
        esc_attr($name),                            // data-name
        esc_attr($brand),                           // data-brand
        $price_rub,                                 // data-price
        (int) $min_order,                           // data-minq
        (int) $available,                           // data-available
        esc_attr($donor),                           // data-donor
        esc_attr($term),                            // data-term
        $disabled,                                  // disabled (дубль)
        esc_url($basket_icon)                       // Иконка корзины
    );

    return $row_html;
}

/**
 * Генерация блока цен (мультицены)
 */
function _generate_price_html($price, $multisite, $final_rate, $markup_type, $markup_value, $markup_scope) {
    if ($multisite && is_array($price)) {
        usort($price, function($a, $b) {
            return intval($a['quantity'] ?? 0) <=> intval($b['quantity'] ?? 0);
        });

        $html = '<div class="multiPrice">';
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

            $html .= sprintf(
                "<div class='multiPrice__item'>
                    <span data-multi='quantity' value='%s'>%s</span> x
                    <span data-multi='price' value='%F'>%s руб.</span>
                </div>",
                esc_attr($pb["quantity"] ?? ''),
                esc_html($pb["quantity"] ?? ''),
                $pb_price_rub,
                number_format($pb_price_rub, 2, ',', ' ')
            );
        }
        $html .= '</div>';
        return $html;
    }

    return number_format($price_rub ?? 0, 2, ',', ' ') . ' руб.';
}

/**
 * Форма для случая "ничего не найдено"
 */
function _get_no_results_form() {
    return '
<div class="component-request-form">
    <h2 class="form-title">К сожалению, мы не нашли товар, но мы можем<br> проработать запрос в ручную</h2>
    <form method="POST" action="/quote-handler.php" class="order-form">
        <input type="hidden" name="action" value="submit_component_request">
        <div class="order-form__input-wrapper">
            <p class="order-form__field first searchForm">
                <label class="order-form__label required-input">Артикул</label>
                <input class="order-form__input" type="text" name="title" required placeholder="C1005X6S1">
            </p>
            <p class="order-form__field first searchForm">
                <label class="order-form__label required-input brend">Бренд</label>
                <input class="order-form__input" type="text" name="brand" required placeholder="Бренд">
            </p>
        </div>
        <div class="order-form__input-wrapper">
            <p class="order-form__field searchForm">
                <label class="order-form__label required-input">Количество</label>
                <input class="order-form__input" type="number" name="quantity" placeholder="1" required min="1">
            </p>
            <p class="order-form__field searchForm">
                <label class="order-form__label">Цена</label>
                <input class="order-form__input" type="number" name="price" min="1" placeholder="1000">
            </p>
        </div>
        <div class="order-form__input-wrapper">
            <p class="order-form__field searchForm">
                <label class="order-form__label required-input">Срок поставки</label>
                <select class="order-form__input option" name="delivery_time" required>
                    <option value="">Выберите срок поставки</option>
                    <option value="up_to_5_weeks">не более 5 недель</option>
                    <option value="more_than_5_weeks">более 5 недель</option>
                </select>
            </p>
            <p class="order-form__field searchForm">
                <label class="order-form__label required-input">Компания</label>
                <input class="order-form__input" type="text" placeholder="1" name="company" required>
            </p>
        </div>
        <p class="order-form__field searchForm">
            <label class="order-form__label textarea">Ваш комментарий</label>
            <textarea class="order-form__textarea" name="comment" rows="4" placeholder="Ваш комментарий"></textarea>
        </p>
        <p class="order-form__submit">
            <button type="submit" id="quoteSubmitBtn" class="order-form__btn btn">Запросить квоту</button>
        </p>
    </form>
</div>
';
}

/**
 * Подготовка артикулов для ссылки
 */
function _prepare_articles_for_link($products, $original_articles = null) {
    if ($original_articles !== null) {
        return $original_articles;
    }

    $articles = [];
    foreach ($products as $product) {
        $article = $product['article'] ?? '';
        if ($article === '' || isset($articles[$article])) {
            continue;
        }
        $qty = (int)($product['desired_quantity'] ?? ($product['minq'] ?? 1));
        $articles[$article] = $qty;
    }

    return $articles;
}

/**
 * Формирование ссылки с параметрами
 */
function _build_query_link($articles) {
    $params = [];
    foreach ($articles as $article => $qty) {
        $params[] = 'article[]=' . urlencode($article);
        $params[] = 'qty[]=' . urlencode((string)$qty);
    }
    $queryString = implode('&', $params);
    return home_url('/') . '?' . $queryString;
}
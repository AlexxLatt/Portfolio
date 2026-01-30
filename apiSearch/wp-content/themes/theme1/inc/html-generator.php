<?
/**
 * Генерирует HTML-таблицу товаров для отображения на фронтенде.
 */

if (!defined('ABSPATH')) {
    exit; // Запрет прямого доступа
}
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
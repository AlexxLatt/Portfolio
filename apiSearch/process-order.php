<?php
// Загружаем WordPress
require_once __DIR__ . '/wp-load.php';

header('Content-Type: application/json; charset=utf-8');

// Только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wp_redirect(home_url('/'));
    exit;
}

// Проверка nonce
if (!wp_verify_nonce($_POST['order_nonce'] ?? '', 'submit_order')) {
    wp_redirect(home_url('/?error=security'));
    exit;
}

/** * 1. ОБРАБОТКА И ВАЛИДАЦИЯ ДАННЫХ
 */

// Убираем лишние слеши (эффект Magic Quotes) и очищаем поля
$name    = sanitize_text_field(stripslashes($_POST['name'] ?? ''));
$email   = sanitize_text_field(stripslashes($_POST['email'] ?? ''));
$company = sanitize_text_field(stripslashes($_POST['company'] ?? ''));
$comment = sanitize_textarea_field(stripslashes($_POST['comment'] ?? ''));

// Валидация телефона (только +7 и цифры, макс 12 символов)
$phone_raw = sanitize_text_field($_POST['phone'] ?? '');
$phone = preg_replace('/[^\d+]/', '', $phone_raw); // Удаляем всё, кроме цифр и +
if (strpos($phone, '8') === 0 && strlen($phone) === 11) {
    $phone = '+7' . substr($phone, 1); // Замена 8 на +7
}
$phone = mb_substr($phone, 0, 12); // Обрезаем до лимита БД

// Валидация ИНН (только цифры, макс 12 символов)
$inn = preg_replace('/\D/', '', $_POST['inn'] ?? '');
$inn = mb_substr($inn, 0, 12);

if (empty($name) || empty($phone) || empty($email)){
    wp_redirect(home_url('/?error=required'));
    exit;
}

/** * 2. РАБОТА С КОРЗИНОЙ
 */
$cart = [];
if (isset($_COOKIE['simple_cart'])) {
    // Тут тоже важно stripslashes, так как в куках кавычки тоже экранируются
    $cart = json_decode(stripslashes($_COOKIE['simple_cart']), true);
    if (!is_array($cart)) $cart = [];
}
$cart_data = json_encode($cart, JSON_UNESCAPED_UNICODE);

$donors = [];
$terms  = [];
foreach ($cart as $item) {
    if (!empty($item['donor'])) $donors[] = $item['donor'];
    if (!empty($item['term']))  $terms[]  = $item['term'];
}

// Готовим строки для БД (обрезаем длину для безопасности, если поля не TEXT)
$donor_str = mb_substr(implode('; ', array_unique($donors)), 0, 255);
$term_str  = mb_substr(implode('; ', array_unique($terms)), 0, 255);

/** * 3. СОХРАНЕНИЕ В БД
 */
global $wpdb;
$table = $wpdb->prefix . 'custom_orders';

$result = $wpdb->insert(
    $table,
    [
        'name'      => $name,
        'phone'     => $phone,
        'email'     => $email,
        'company'   => $company,
        'donor'     => $donor_str,
        'term'      => $term_str,
        'inn'       => $inn,
        'comment'   => $comment,
        'cart_data' => $cart_data,
    ],
    ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
);

/** * 4. ОТПРАВКА ПИСЕМ
 */
if ($result !== false) {
    $order_id = $wpdb->insert_id;
    $site_name = get_bloginfo('name');
    $order_date = date('d.m.Y');
    
    $subject = sprintf('Оформление заказа №%d', $order_id);
    $subjectUser = sprintf('Ваша заявка №%d от %s принята в работу.', $order_id, $order_date);

    // Таблица товаров для письма
    $cart_table = '';
    if (!empty($cart)) {
        $cart_table = '<h3>Товары в заказе:</h3><table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
        $cart_table .= '<thead><tr><th>Наименование</th><th>Поставщик</th><th>Бренд</th><th>Цена</th><th>Срок</th><th>Кол-во</th><th>Сумма</th></tr></thead><tbody>';

        foreach ($cart as $item) {
            $nameA   = esc_html($item['name'] ?? '—');
            $donor   = esc_html($item['donor'] ?? '—');
            $brand   = esc_html($item['brand'] ?? '—');
            $price   = number_format($item['price'] ?? 0, 2, ',', ' ');
            $term    = esc_html($item['term'] ?? '—');
            $qty     = (int)($item['quantity'] ?? 0);
            $total   = number_format($item['total'] ?? 0, 2, ',', ' ');
        
            $cart_table .= "<tr><td>{$nameA}</td><td>{$donor}</td><td>{$brand}</td><td>{$price} ₽</td><td>{$term}</td><td>{$qty}</td><td>{$total} ₽</td></tr>";
        }
        $cart_table .= '</tbody></table>';
    }

    $message = "
        <div style='padding:20px; font-family: sans-serif;'>
            <p><strong>ФИО:</strong> {$name}</p>
            <p><strong>Телефон:</strong> {$phone}</p>
            <p><strong>Компания:</strong> {$company}</p>
            <p><strong>ИНН:</strong> {$inn}</p>
            <p><strong>Комментарий:</strong><br>{$comment}</p>
            {$cart_table}
        </div>";
    
    $messageUser = "
        <div style='font-family: sans-serif;'>
            <p>Ваша заявка №{$order_id} от {$order_date} принята в работу.</p>
            <p>В ближайшее время Вам будет выставлен счет на оплату.</p>
            <p>Благодарим за доверие!</p>
        </div>";
    
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <my@email.com>'
    ];
    //Отправка почты
    wp_mail("my@email.com", $subject, $message, $headers);
    wp_mail($email, $subjectUser, $messageUser, $headers);

    // Очищаем куки
    setcookie('simple_cart', '', time() - 3600, '/');
    setcookie('cart_count', '', time() - 3600, '/');

    wp_redirect(home_url('/thank-you/?order_id=' . $order_id));
    exit;
} else {
    // В чистовом варианте верните редирект на ?error=db
    wp_die("Ошибка БД: " . $wpdb->last_error); 
    exit;
}
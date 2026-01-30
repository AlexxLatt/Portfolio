<?php
// Загружаем WordPress
require_once __DIR__ . '/../../../wp-load.php';

// Разрешаем только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wp_die('Метод не разрешён.');
}

// Защита от CSRF (опционально, но желательно)
// Можно добавить nonce, но для простоты пропустим

// Получаем данные
$name     = sanitize_text_field($_POST['name'] ?? '');
$phone    = sanitize_text_field($_POST['phone'] ?? '');
$company  = sanitize_text_field($_POST['company'] ?? '');
$inn      = sanitize_text_field($_POST['inn'] ?? '');
$comment  = sanitize_textarea_field($_POST['comment'] ?? '');

// Проверяем корзину из куки
$cart = [];
if (isset($_COOKIE['simple_cart'])) {
    $cart = json_decode(stripslashes($_COOKIE['simple_cart']), true);
    if (!is_array($cart)) $cart = [];
}

// Валидация
if (empty($name) || empty($phone)) {
    $error = 'Пожалуйста, укажите ФИО и телефон.';
} elseif (empty($cart)) {
    $error = 'Ваша корзина пуста.';
} else {
    global $wpdb;
    $table = $wpdb->prefix . 'custom_orders';

    $result = $wpdb->insert(
        $table,
        [
            'name'      => $name,
            'phone'     => $phone,
            'company'   => $company,
            'inn'       => $inn,
            'comment'   => $comment,
            'cart_data' => json_encode($cart, JSON_UNESCAPED_UNICODE),
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s']
    );

    if ($result !== false) {
        // Отправка письма
        $to = get_option('admin_email');
        $subject = 'Новый заказ на сайте ' . get_bloginfo('name');

        $message = "<h2>Новый заказ</h2>";
        $message .= "<p><strong>ФИО:</strong> " . esc_html($name) . "</p>";
        $message .= "<p><strong>Телефон:</strong> " . esc_html($phone) . "</p>";
        if ($company) $message .= "<p><strong>Компания:</strong> " . esc_html($company) . "</p>";
        if ($inn) $message .= "<p><strong>ИНН:</strong> " . esc_html($inn) . "</p>";
        if ($comment) $message .= "<p><strong>Комментарий:</strong> " . esc_html($comment) . "</p>";

        $message .= "<h3>Товары:</h3><ul>";
        foreach ($cart as $item) {
            $message .= "<li>" . esc_html($item['name']) .
                        " (арт. " . esc_html($item['article'] ?? '') . ") — " .
                        esc_html($item['quantity']) . " шт. по " .
                        number_format($item['price'], 0, '', ' ') . " руб.</li>";
        }
        $message .= "</ul>";

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($to, $subject, $message, $headers);

        // Очищаем корзину
        setcookie('simple_cart', '', time() - 3600, '/');
        setcookie('cart_count', '', time() - 3600, '/');

        // Перенаправляем на главную с сообщением
        wp_redirect(home_url('/?order_success=1'));
        exit;
    } else {
        $error = 'Ошибка при сохранении заказа.';
    }
}

// Если ошибка — возвращаем на страницу заказа с ошибкой
$error_url = home_url('/place_an_order/?error=' . urlencode($error));
wp_redirect($error_url);
exit;

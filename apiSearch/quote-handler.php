<?php
// Загружаем WordPress
require_once __DIR__ . '/wp-load.php';

header('Content-Type: application/json; charset=utf-8');

// Получаем данные
$title = trim($_POST['title'] ?? '');
$brand = trim($_POST['brand'] ?? '');
$quantity = (int)($_POST['quantity'] ?? 1);
$price = isset($_POST['price']) ? (float)$_POST['price'] : null;
$delivery_time = $_POST['delivery_time'] ?? '';
$company = trim($_POST['company'] ?? '');
$comment = trim($_POST['comment'] ?? '');

// Простая валидация
if (empty($title) || empty($brand) || empty($company) || empty($delivery_time)) {
    echo json_encode(['success' => false, 'data' => 'Заполните все обязательные поля.']);
    exit;
}

// Преобразуем срок
if ($delivery_time === 'up_to_5_weeks') {
    $delivery_label = 'не более 5 недель';
} elseif ($delivery_time === 'more_than_5_weeks') {
    $delivery_label = 'более 5 недель';
} else {
    $delivery_label = $delivery_time;
}

// Формируем письмо
$message = "Новый запрос на квотирование\n\n";
$message .= "Артикул: $title\n";
$message .= "Бренд: $brand\n";
$message .= "Количество: $quantity шт.\n";
if ($price !== null) $message .= "Цена: " . number_format($price, 2, ',', ' ') . " руб.\n";
$message .= "Срок поставки: $delivery_label\n";
$message .= "Компания: $company\n";
if ($comment) $message .= "Комментарий: $comment\n";

// Отправляем
$to = 'my@email.com';
$subject = 'Запрос квоты: ' . $title;

// Используем wp_mail (требует wp-load.php)
$sent = wp_mail($to, $subject, $message, [
    'Content-Type: text/plain; charset=UTF-8',
    'From: MegaAtom <my@email.com>'
]);

if ($sent) {
    echo json_encode(['success' => true, 'data' => 'Запрос на квотирование успешно отправлен.']);
} else {
    echo json_encode(['success' => false, 'data' => 'Не удалось отправить письмо.']);
}
<?php
/**
 * Файл: inc/orders-page.php
 * Назначение: Регистрация и отображение страницы "Заказы" в админке WordPress.
 */

if (!defined('ABSPATH')) {
    exit; // Запрет прямого доступа
}

/**
 * Добавляет пункт меню "Заказы" в админ-панель WordPress.
 */
add_action('admin_menu', 'add_custom_orders_menu');
function add_custom_orders_menu() {
    add_menu_page(
        'Заказы',                   // Заголовок страницы
        'Заказы',                   // Название в меню
        'manage_options',           // Кто может видеть (админы)
        'custom-orders',            // Slug
        'custom_orders_list_page',  // Функция вывода
        'dashicons-cart',           // Иконка
        57                          // Позиция
    );
}

/**
 * Выводит список заказов из таблицы wp_custom_orders.
 */
function custom_orders_list_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_orders';

    // Получаем все заказы
    $orders = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC");

    echo '<div class="wrap">';
    echo '<h1>Заказы</h1>';

    if (empty($orders)) {
        echo '<p>Нет заказов.</p>';
        echo '</div>';
        return;
    }

    echo '<table class="wp-list-table widefat fixed striped posts">';
    echo '<thead><tr>
        <th>ID</th>
        <th>ФИО</th>
        <th>Телефон</th>
        <th>Email</th>
        <th>Компания</th>
        <th>ИНН</th>
        <th>Поставщик</th>
        <th>Срок</th>
        <th>Дата</th>
        <th>Товары</th>
    </tr></thead>';
    echo '<tbody>';

    foreach ($orders as $order) {
        $cart_items = json_decode($order->cart_data, true);
        $items_html = '';
        if (is_array($cart_items)) {
            foreach ($cart_items as $item) {
                $items_html .= esc_html($item['name']) . ' (' . esc_html($item['quantity']) . ' шт.)<br>';
            }
        }

        echo '<tr>';
        echo '<td>' . esc_html($order->id) . '</td>';
        echo '<td>' . esc_html($order->name) . '</td>';
        echo '<td>' . esc_html($order->phone) . '</td>';
        echo '<td>' . esc_html($order->email) . '</td>';
        echo '<td>' . esc_html($order->company) . '</td>';
        echo '<td>' . esc_html($order->inn) . '</td>';
        echo '<td>' . esc_html($order->donor) . '</td>';
        echo '<td>' . esc_html($order->term) . '</td>';
        echo '<td>' . esc_html($order->created_at) . '</td>';
        echo '<td>' . $items_html . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}
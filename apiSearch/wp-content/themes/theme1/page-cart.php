<?php
/**
 * Template Name: Страница корзины
 */
get_header();

$cart_json = $_COOKIE['simple_cart'] ?? '';
$cart = [];

if (!empty($cart_json)) {
    $cart_json_clean = stripslashes($cart_json);
    $cart = json_decode($cart_json_clean, true);
    if (!is_array($cart)) {
        $cart = [];
    }
}
?>
<script>
  if (performance.navigation.type === performance.navigation.TYPE_RELOAD) {
    // уже перезагружалась — не делаем ничего
  } else {
    // первый вход — перезагружаем, чтобы учесть актуальные куки
    location.reload();
  }
</script>
<main class="main">
  <div class="container">

      <div class="<?php echo esc_html($className); ?> page-content">

        <div class="page-content__header page-header">
          <?php
          // хлебные крошки
          if (function_exists('dimox_breadcrumbs')) dimox_breadcrumbs();
          ?>

          <?php
          // заголовок страницы
          the_title('<h1 class="page-header__title">', '</h1>');
          ?>
        </div><!-- // page-header -->

    </div><!-- // page-wrap -->
  </div><!-- // container -->

 <?php
        // Начинаем цикл WordPress
        if ( have_posts() ) :
            while ( have_posts() ) :
                the_post();
                // Выводим заголовок (опционально)
                // the_title( '<h1>', '</h1>' );

                // Выводим основное содержимое страницы
                the_content();
            endwhile;
        endif;
?>

<div class="container cart-page">
    <?php if (empty($cart)): ?>
        <div class="product-search-no-results text-center py-5">
            <p class="mt-3">Ваша корзина пуста.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive mt-4 overflow">
            <table class="product-search-table table-striped table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>

                        <th scope="col">Поставщик</th>
                        <th scope="col">Наименование</th>
                        <th scope="col">Бренд</th>
                        <th scope="col">Срок</th>
                        <th scope="col">Цена, руб.</th>
                        <th scope="col">Кол-во</th>
                        <th scope="col">Сумма, руб.</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart as $index => $item): ?>
                        <tr class="column_head" data-cart-index="<?= (int) $index ?>">
                            <td class="product-name text-nowrap" data-label="Поставщик">
                                <?= esc_html($item['donor'] ?? '—') ?>
                            </td>
                            <td class="product-name text-nowrap" data-label="Наименование">
                                <?= esc_html($item['name'] ?? '—') ?>
                            </td>
                            <td class="brand text-nowrap" data-label="Бренд">
                                <?= esc_html($item['brand'] ?? '—') ?>
                            </td>
                            <td class="product-name text-nowrap" data-label="Срок">
                                <?= esc_html($item['term'] ?? '—') ?>
                            </td>
                            <td class="price-rub" data-label="Цена">
                                <?= number_format($item['price'] ?? 0, 4, ',', ' ') ?> руб.
                            </td>
                            <td class="quantity" data-label="Кол-во">
                                <?= (int)($item['quantity'] ?? 0) ?> шт.
                            </td>
                            <td class="total-rub" data-label="Сумма">
                                <?= number_format($item['total'] ?? 0, 2, ',', ' ') ?> руб.
                            </td>
                            <td class="text-center" data-label="Действия">
                                <button 
                                    type="button" 
                                    class="btn btn-sm btn-danger remove-from-cart"
                                    data-index="<?= (int) $index ?>"
                                    title="Удалить товар"
                                >
                                    Удалить
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Итоговая сумма (опционально) -->
        <?php
        $total_sum = array_sum(array_column($cart, 'total'));
        if ($total_sum > 0): ?>
            <div class="mt-4 p-3 bg-light rounded text-end">
                <strong>Общая сумма: <?= number_format($total_sum, 2, ',', ' ') ?> руб.</strong>
            </div>
        <?php endif; ?>
      
      <?php if($total_sum < 1000):?>
      <div class="order_error">Сумма заказа должна быть больше 1000р</div>
      <?php endif;?>
      
        <div class="place_an_order">
            <a href="https://megaatom.com/place_an_order/" class="btn btn-sm  <?= $total_sum < 1000 ? 'disabled': ''; ?>">Оформить заказ</a>
        </div>
       
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.remove-from-cart').forEach(button => {
        button.addEventListener('click', function() {
            if (!confirm('Удалить товар из корзины?')) {
                return;
            }
            let positionsCount;
            const index = parseInt(this.getAttribute('data-index'));

            // Получаем текущую корзину из куки
            let cart = [];
            const cartCookie = document.cookie
                .split('; ')
                .find(row => row.startsWith('simple_cart='));
            
            if (cartCookie) {
                try {
                    cart = JSON.parse(decodeURIComponent(cartCookie.split('=')[1]));
                } catch (e) {
                    alert('Ошибка чтения корзины');
                    return;
                }
            }

            // Удаляем товар по индексу
            if (index >= 0 && index < cart.length) {
                cart.splice(index, 1);
                positionsCount =  cart.length;
            } else {
                alert('Товар не найден');
                return;
            }
         

            // Обновляем куки
            const expires = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toUTCString();
            document.cookie = `simple_cart=${encodeURIComponent(JSON.stringify(cart))}; expires=${expires}; path=/; SameSite=Lax`;
            document.cookie = `cart_count=${positionsCount}; expires=${expires}; path=/; SameSite=Lax`;

            location.reload();
        });
    });
});
</script>
<?php get_footer(); ?>
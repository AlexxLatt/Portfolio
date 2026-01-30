<?php
/**
 * Template Name: Оформление заказа (custom)
 */
get_header();

// Обработка формы

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

<?php if (empty($cart)): ?>
        <div class="product-search-no-results text-center py-5">
            <p class="mt-3">Ваша корзина пуста.</p>
        </div>
<?php else: ?>



<h3 class="order-title">
    Мы работаем только с юридическими лицами (ооо, ип)
<h3>
<div class="order-form-container"> <!-- это отдельный блок-обёртка -->

<form class="order-form" method="POST" action="<?php echo esc_url(home_url('/process-order.php')); ?>">
    <?php wp_nonce_field('submit_order', 'order_nonce'); ?>

    <div class="order-form__input-wrapper">
        
        <p class="order-form__field first">
          <label class="order-form__label required-input">ФИО </label>
            <input class="order-form__input" type="text" name="name" required>
          </label>
        </p>
    
        <p class="order-form__field first">
          <label class="order-form__label required-input tel">Телефон </label>
           <input 
                class="order-form__input" 
                type="tel" 
                name="phone" 
                placeholder="+79001234567" 
                maxlength="12"
                pattern="^\+7\d{10}$"
                title="Введите номер в формате +7 и 10 цифр (например, +79001234567)"
                required
            >
        </p>
        
    </div>
    
    <div class="order-form__input-wrapper">
        
        
        <p class="order-form__field">
          <label class="order-form__label required-input">Email </label>
            <input class="order-form__input" type="email" name="email" required>
        </p>
    
    
        <p class="order-form__field">
          <label class="order-form__label required-input">ИНН </label>
            <input 
                class="order-form__input" 
                maxlength="12"
                pattern="\d{10,12}" 
                title="ИНН должен содержать от 10 до 12 цифр" 
                type="text" 
                name="inn" 
                required
            >
         
        </p>
        
    </div>
    
    <div class="order-form__input-wrapper">
        <p class="order-form__field">
          <label class="order-form__label required-input">Название компании </label>
            <input class="order-form__input" type="text" name="company" required>
        
        </p>
       
        
    </div>
    
    
    
    <p class="order-form__field">
      <label class="order-form__label">Комментарий к заказу<br>
        <textarea class="order-form__textarea" name="comment" rows="4"></textarea>
      </label>
    </p>

    <p class="order-form__submit">
      <button type="submit" class="order-form__btn btn ">Оформить заказ</button>
    </p>
  </form>
</div>
<?php endif; ?>


<?php get_footer(); ?>

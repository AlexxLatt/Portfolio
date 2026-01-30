<?php
/*
 Template Name: Главная страница сайта
 *
 * Шаблон для статической Главной с готовыми блоками
 * Настраивается в консоли темы в разделе "Главная страница"
 */

get_header();

// Получаем данные из консоли Titan Framework
$option = TitanFramework::getInstance('theme1');
$items  = $option->getOption('sections');

// Убедимся, что $items — массив
$items = is_array($items) ? $items : [];
?>

<main class="main">

	<?php foreach ($items as $item): ?>
        <?php 
            if ($item === 'poster'){
                continue;    
            }
        ?>
        
        
		<?php if ($item === 'cards'): ?>
			<!-- Заменяем секцию 'cards' на кастомный блок -->
			<section class="home-cards-custom">
				<?php
				// Проверяем наличие параметров article и qty
				if (isset($_GET['article']) && !empty($_GET['article'])):
					$products = get_products_data(null);
					echo do_shortcode('[product_search]');
					echo '<div class="product-search-container" id="getTable">';
					echo generate_products_table($products);
					echo '</div>';
				else:
					// Опционально: показать обычный контент секции cards
					// Или просто ничего не показывать
					if (have_posts()):
						while (have_posts()): the_post();
							echo do_shortcode('[product_search]');
							the_content();
						endwhile;
					endif;
				endif;
				?>
			</section>

		<?php else: ?>
			<!-- Все остальные секции подключаются как обычно -->
			<?php get_template_part('inc/home/home', $item); ?>

		<?php endif; ?>

	<?php endforeach; ?>

	<!-- Контактная секция — всегда внизу, один раз (как в оригинале) -->
	<?php get_template_part('inc/home/home-contact'); ?>

</main>
<?php get_footer(); ?>
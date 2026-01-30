<?php



//Новый кастомный код ==========================================
require_once get_template_directory() . '/inc/product-search.php';

require_once get_template_directory() . '/inc/orders-page.php';
//Новый кастомный код ========================================== (конец)



add_action('admin_post_submit_custom_order', 'handle_custom_order');
add_action('admin_post_nopriv_submit_custom_order', 'handle_custom_order');

function handle_custom_order() {
    error_log('Функция запущена');
    if (!wp_verify_nonce($_POST['order_nonce'] ?? '', 'submit_order')) {
    error_log('Nonce не прошёл');
    wp_redirect(home_url('/?error=nonce'));
    exit;
}
    
    wp_redirect(home_url('/thank-you')); // или просто на главную
    exit;
}

if (!defined('VERSION')) {
	// версия темы
	define('VERSION', '1.0.0');
}

if (!function_exists('theme_support')) :
	function theme_support()
	{

		// путь к директории локализации
		load_theme_textdomain('theme1', get_template_directory() . '/languages');

		// ссылки на фиды
		add_theme_support('automatic-feed-links');

		// title сайта
		add_theme_support('title-tag');

		// миниатюры  
		add_theme_support('post-thumbnails');

		// меню сайта
		register_nav_menus(
			array(
				'primary' => esc_html__('Главное меню', 'gorn-2'),
				'footer-menu' => esc_html__('Дополнительное меню в подвале', 'gorn-2'),
			)
		);

		/*
		* Поддержка HTML5
		*/
		add_theme_support(
			'html5',
			array(
				'search-form',
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'style',
				'script',
			)
		);

		// Кастомный фон
		add_theme_support(
			'custom-background',
			apply_filters(
				'mp3_custom_background_args',
				array(
					'default-color' => 'ffffff',
					'default-image' => '',
				)
			)
		);

		// отключаем блочный редактор виджетов
		remove_theme_support('widgets-block-editor');

		//адаптивное видео
		add_theme_support('responsive-embeds');

		// стили для редактора
		add_theme_support('editor-styles');
		add_editor_style('style-editor.css');

		/**
		 * Кастомное лого в режиме превью
		 */
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 300,
				'width'       => 75,
				'flex-width'  => true,
				'flex-height' => true,
			)
		);
	}

endif;
add_action('after_setup_theme', 'theme_support');



/**
 * Виджеты
 */
function theme_widgets_init()
{

	register_sidebar(
		array(
			'name'          => esc_html__('Сайдбар в блоге, записях', 'theme1'),
			'id'            => 'sidebar-1',
			'description'   => esc_html__('Добавьте виджеты.', 'theme1'),
			'before_widget' => '<div id="%1$s" class="widget %2$s post-content">',
			'after_widget'  => '</div>',
			'before_title'  => '<span class="widget-title">',
			'after_title'   => '</span>',
		)
	);

	register_sidebar(
		array(
			'name'          => esc_html__('Сайдбар в каталоге и карточках', 'theme1'),
			'id'            => 'sidebar-2',
			'description'   => esc_html__('Добавьте виджеты.', 'theme1'),
			'before_widget' => '<div id="%1$s" class="widget %2$s post-content">',
			'after_widget'  => '</div>',
			'before_title'  => '<span class="footer-widget__title widget-title">',
			'after_title'   => '</span>',
		)
	);
}
add_action('widgets_init', 'theme_widgets_init');


/**
 * Подключением стилей и скриптов
 */
function mp_scripts()
{
	wp_enqueue_style('theme-style', get_stylesheet_uri(), array(), VERSION);


	if (is_front_page() || is_singular('products_card')) {
		wp_enqueue_script('theme-gallery-script', get_template_directory_uri() . '/assets/js/gallery.js', array('jquery'), VERSION, true);
	}

	wp_enqueue_script('theme-vendor-script', get_template_directory_uri() . '/assets/js/vendor.js', array('jquery'), VERSION, true);

	wp_enqueue_script('theme-custom-script', get_template_directory_uri() . '/assets/js/custom.js', array('jquery'), VERSION, true);

	if (is_singular() && comments_open() && get_option('thread_comments')) {
		wp_enqueue_script('comment-reply');
	}
}
add_action('wp_enqueue_scripts', 'mp_scripts');



/**
 Активация темы, не удаляем эту строку
 */
require_once('admin/class-gp-theme-options.php');



/**
 * Функции темы
 */
require get_template_directory() . '/inc/template-functions.php';


/**
 * Категории для продуктов
 */
require get_template_directory() . '/inc/products/products-taxonomy.php';


/**
 * Изображения для категорий
 */
require get_template_directory() . '/inc/products/products-taxonomy-img.php';


/**
 * Кастомный тип поста - продукты
 */
require get_template_directory() . '/inc/products/products-post-type.php';


/**
 * Слайдер для продуктов
 */
require get_template_directory() . '/inc/products/products-slider.php';


/**
 * Метабокс продукты
 */
require get_template_directory() . '/inc/products/products-metabox.php';


/**
 * Изменяем комментарии и добавляем к ним микроразметку
 */
require get_template_directory() . '/inc/template-parts/comment-atts.php';


/**
 * Функции для продуктов
 */
require get_template_directory() . '/inc/products/products-functions.php';


/**
 * Выведем пользовательские стили в head
 */
require get_template_directory() . '/inc/header-styles.php';


/**
 * Оптимизация кода
 */
require get_template_directory() . '/inc/optimize.php';


/**
 * Хлебные крошки
 */
require get_template_directory() . '/inc/template-parts/breadcrumbs.php';


/**
 * Отключение сайдбаоа
 */
require get_template_directory() . '/inc/sidebar-metabox.php';


/**
 * Обновление темы
 */
require get_template_directory() . '/admin/plugin-update-checker/plugin-update-checker.php';


/**
 * Обрезаем и подгоняем миниатюры, если нужно
 */
require get_template_directory() . '/assets/aq_resizer.php';


// фиксим баг с лишним тэгом абзаца в Contact Form 7  
add_filter('wpcf7_autop_or_not', '__return_false');

/**
 Интеграция с One Demo click import
*/
require get_template_directory() . '/admin/options/ocdi.php';


/***Если нужно добавить какой-то код в functions.php, разместите его под этой строкой ***/


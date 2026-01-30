<?php
/**
 * Файл: inc/handlers-shortcode.php
 * Назначение: AJAX-обработчики загрузка Excel и шорткод
 */

if (!defined('ABSPATH')) {
    exit;
}

// Подключение скриптов
add_action('wp_enqueue_scripts', 'product_search_enqueue_scripts', 100);
function product_search_enqueue_scripts() {
    if (!wp_script_is('jquery', 'enqueued')) {
        wp_enqueue_script('jquery');
    }
    wp_enqueue_style('dashicons');
}

// ==========================================
// AJAX ОБРАБОТЧИКИ
// ==========================================

add_action('wp_ajax_nopriv_product_search_by_article', 'handle_product_search_by_article');
add_action('wp_ajax_product_search_by_article', 'handle_product_search_by_article');

function handle_product_search_by_article() {
    $input = sanitize_textarea_field($_POST['article'] ?? '');
    
    if (empty(trim($input))) {
        wp_send_json_error(['message' => 'Пустой запрос']);
    }

    $original_articles = _parse_articles_from_text($input);
    $products = get_products_data($input);
    $html = generate_products_table($products, $original_articles);

    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_nopriv_product_search_by_list', 'handle_product_search_by_list');
add_action('wp_ajax_product_search_by_list', 'handle_product_search_by_list');

function handle_product_search_by_list() {
    check_ajax_referer('product_search_nonce', 'security');

    $articles_input = isset($_POST['articles']) ? sanitize_textarea_field($_POST['articles']) : '';

    if (empty(trim($articles_input))) {
        wp_send_json_error(['message' => 'Список артикулов пуст']);
    }

    $original_articles = _parse_articles_from_text($articles_input);
    $products = get_products_data($articles_input);
    $html = generate_products_table($products, $original_articles);

    wp_send_json_success(['html' => $html]);
}

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

add_action('wp_ajax_nopriv_product_search_by_file', 'handle_product_search_by_file');
add_action('wp_ajax_product_search_by_file', 'handle_product_search_by_file');

function handle_product_search_by_file() {
    check_ajax_referer('product_search_nonce', 'security');

    if (!isset($_FILES['file']) || empty($_FILES['file']['tmp_name'])) {
        wp_send_json_error(['message' => 'Файл не загружен']);
    }

    $file = $_FILES['file'];
    $file_path = $file['tmp_name'];
    $file_name = $file['name'];
    $articles = [];

    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    try {
        if ($ext === 'csv') {
            $content = file_get_contents($file_path);
            $content = str_replace(["\r\n", "\r"], "\n", $content);
            $rows = str_getcsv($content, "\n");

            foreach ($rows as $row) {
                $row = trim($row);
                if ($row === '') continue;

                $cols = str_getcsv($row, ',');
                $article = !empty($cols[0]) ? trim($cols[0]) : null;
                $qty = !empty($cols[1]) ? (int) trim($cols[1]) : 1;

                if ($article && $qty > 0 && _validate_article($article)) {
                    $articles[$article] = $qty;
                }
            }
        } else {
            $reader = IOFactory::createReaderForFile($file_path);
            $spreadsheet = $reader->load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray(null, true, true, true);

            foreach ($rows as $row) {
                $article = !empty($row['A']) ? trim($row['A']) : null;
                $qty = !empty($row['B']) ? (int) $row['B'] : 1;

                if ($article && $qty > 0 && _validate_article($article)) {
                    $articles[$article] = $qty;
                }
            }
        }
    } catch (ReaderException $e) {
        wp_send_json_error(['message' => 'Ошибка чтения файла: некорректный формат Excel.']);
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Ошибка обработки файла: ' . $e->getMessage()]);
    }

    if (empty($articles)) {
        wp_send_json_error(['message' => 'Не найдено корректных артикулов в файле.']);
    }

    $products = get_products_data($articles);
    $html = generate_products_table($products, $articles);

    wp_send_json_success(['html' => $html]);
}

/**
 * Парсит артикулы из текста
 */
function _parse_articles_from_text($input) {
    $articles = [];
    $input_clean = trim(str_replace(["\r\n", "\r"], "\n", $input));

    if ($input_clean === '') {
        return $articles;
    }

    $lines = explode("\n", $input_clean);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        if (strpos($line, ':') !== false) {
            $parts = explode(':', $line, 2);
            $article = trim($parts[0]);
            $qty = max(1, (int) trim($parts[1]));
        } else {
            $article = $line;
            $qty = 1;
        }

        if ($article !== '' && _validate_article($article)) {
            $articles[$article] = $qty;
        }
    }

    return $articles;
}

// ==========================================
// ШОРТКОД
// ==========================================

add_shortcode('product_search', 'product_search_shortcode');

function product_search_shortcode() {
    $search = get_stylesheet_directory_uri() . '/images/search.png';
    $searchList = get_stylesheet_directory_uri() . '/images/search-list.png';

    ob_start();
    ?>
    <div class="product-search-container">
        <div class="section__header">
            <h2 class="section__title section__title--center">Глобальный поиск электронных компонентов</h2>
        </div>
        <div class="modal-shadow" data-shadow data-modal-close></div>
        <section class="search">
            <span class="search-help">
                <span class="search-help__label">Как пользоваться поиском</span>
                <span class="search-help__icon" aria-label="Показать подсказку по поиску" tabindex="0">ⓘ</span>
                <div class="search-help__tooltip" role="tooltip">
                    <div class="search-help__step">
                        <img src="<?php echo esc_url($search); ?>" alt="Поиск по артикулу" class="search-help__image">
                        <p>Для поиска укажите <strong>Артикул</strong> и <strong>Количество</strong></p>
                    </div>
                    <div class="search-help__step">
                        <img src="<?php echo esc_url($searchList); ?>" alt="Поиск по списку компонентов" class="search-help__image">
                        <p>Для поиска по списку введите через новую строку<br><strong>Артикул</strong> или <strong>Артикул:Количество</strong></p>
                    </div>
                </div>
            </span>
            <form class="search__form" id="product-search-form">
                <div class="search__input-container">
                    <input type="search" class="search__input single-mode article-search" id="single-search-input" placeholder="Поиск по артикулу" aria-label="Поиск по артикулу">
                    <input type="search" class="search__input single-mode quanity-search" id="single-search-input-quanity" placeholder="Кол-во" aria-label="Кол-во">
                    <textarea class="search__input list-mode disable" id="list-search-input" rows="1" placeholder="Поиск по артикулу"></textarea>
                </div>
                <div class="search__buttons">
                    <button class="search__submit" type="submit">Найти</button>
                    <div class="search__upload" data-upload>
                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="28" viewBox="0 0 26 28" fill="none" aria-hidden="true">
                            <!-- SVG content -->
                            <path d="M20.1 8.67502L20.6787 8.09502C21.1395 7.63404 21.7646 7.375 22.4164 7.37488C23.0682 7.37476 23.6934 7.63358 24.1543 8.09439C24.6153 8.5552 24.8744 9.18027 24.8745 9.83207C24.8746 10.4839 24.6158 11.109 24.155 11.57L23.5762 12.15M20.1 8.67502C20.1 8.67502 20.1725 9.90502 21.2587 10.9913C22.345 12.0775 23.5762 12.15 23.5762 12.15M20.1 8.67502L14.775 14C14.4125 14.36 14.2325 14.5413 14.0775 14.74C13.8941 14.975 13.7383 15.2275 13.61 15.4975C13.5012 15.725 13.4212 15.9663 13.26 16.45L12.7437 18L12.5762 18.5013M23.5762 12.15L18.2512 17.475C17.8887 17.8375 17.7087 18.0175 17.51 18.1725C17.275 18.3558 17.0225 18.5117 16.7525 18.64C16.525 18.7488 16.2837 18.8288 15.8 18.99L14.25 19.5063L13.7487 19.6738M12.5762 18.5013L12.41 19.0038C12.3712 19.1204 12.3657 19.2455 12.394 19.3651C12.4223 19.4847 12.4834 19.5941 12.5703 19.681C12.6572 19.7679 12.7665 19.8289 12.8861 19.8572C13.0057 19.8855 13.1308 19.88 13.2475 19.8413L13.7487 19.6738M12.5762 18.5013L13.7487 19.6738" stroke="#FF5E14" stroke-width="2.25" />
                            <path d="M7.375 14.875H10.5M7.375 9.875H15.5M7.375 19.875H9.25M1.125 16.125V11.125C1.125 6.41125 1.125 4.05375 2.59 2.59C4.055 1.12625 6.41125 1.125 11.125 1.125H13.625C18.3387 1.125 20.6962 1.125 2.59 2.59M23.625 16.125C23.625 20.8387 23.625 23.1962 22.16 24.66M22.16 24.66C20.6962 26.125 18.3387 26.125 13.625 26.125H11.125C6.41125 26.125 4.05375 26.125 2.59 24.66M22.16 24.66C23.34 23.4812 23.5687 21.725 23.6137 18.625" stroke="#FF5E14" stroke-width="2.25" stroke-linecap="round" />
                        </svg>
                        <span class="search__upload-text">Загрузка файла Excel</span>
                    </div>
                </div>
                <div class="search__mode">
                    <div class="search__mode-item">
                        <label class="search__mode-label" for="search-mode-single">
                            <input class="search__mode-input" type="radio" id="search-mode-single" name="search-mode" checked />
                            <span class="search__mode-input--custom"></span>
                            По одному компоненту
                        </label>
                    </div>
                    <div class="search__mode-item">
                        <label class="search__mode-label" for="search-mode-list">
                            <input class="search__mode-input" type="radio" id="search-mode-list" name="search-mode" />
                            <span class="search__mode-input--custom"></span>
                            По списку компонентов
                        </label>
                    </div>
                </div>
            </form>
        </section>
        <div class="modal-upload" data-modal-upload>
            <svg class="modal-upload__close" data-modal-close xmlns="http://www.w3.org/2000/svg" width="23" height="23" viewBox="0 0 23 23" fill="none">
                <path d="M1.125 1.125L11.125 11.125M21.125 21.125L11.125 11.125M11.125 11.125L21.125 1.125M11.125 11.125L1.125 21.125" stroke="#333646" stroke-width="2.25" stroke-linecap="round" />
            </svg>
            <span class="modal-upload__text">Выберите или перетащите сюда Excel файл. Максимальное кол-во строк 100. Внимание! Файл должен быть заполнен строго по примеру файла, без указания иных символов и пробелов в колонке с количеством</span>
            <div class="modal-upload__field" data-dropzone>
                <input type="file" class="modal-upload__input" id="excel-upload" accept=".xls,.xlsx">
                <label class="modal-upload__label" for="excel-upload">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 15 15" fill="none">
                        <path d="M4.75633 9.47048L9.4705 4.75632M2.988 6.52382L1.80966 7.70215C1.50013 8.01169 1.25459 8.37916 1.08707 8.78359C0.91955 9.18802 0.833328 9.62148 0.833328 10.0592C0.833328 10.497 0.91955 10.9304 1.08707 11.3349C1.25459 11.7393 1.50013 12.1068 1.80966 12.4163C2.1192 12.7259 2.48667 12.9714 2.8911 13.1389C3.29553 13.3064 3.729 13.3927 4.16675 13.3927C4.6045 13.3927 5.03796 13.3064 5.44239 13.1389C5.84682 12.9714 6.21429 12.7259 6.52383 12.4163L7.7005 11.238M6.523 2.98798L7.70133 1.80965C8.01087 1.50011 8.37834 1.25457 8.78277 1.08705C9.1872 0.919534 9.62066 0.833313 10.0584 0.833313C10.4962 0.833313 10.9296 0.919534 11.3341 1.08705C11.7385 1.25457 12.106 1.50011 12.4155 1.80965C12.725 2.11919 12.9706 2.48666 13.1381 2.89109C13.3056 3.29552 13.3918 3.72898 13.3918 4.16673C13.3918 4.60448 13.3056 5.03795 13.1381 5.44238C12.9706 5.84681 12.725 6.21428 12.4155 6.52382L11.2372 7.70215" stroke="#FF5E14" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span class="modal-upload__filename" data-upload-filename>Выбрать файл</span>
                </label>
            </div>
            <a class="modal-upload__example" href="https://megaatom.com/wp-content/uploads/Книга1.xlsx">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="18" viewBox="0 0 14 18" fill="none">
                    <path d="M7.625 1.125V5.45833C7.625 5.67935 7.7128 5.89131 7.86908 6.04759C8.02536 6.20387 8.23732 6.29167 8.45833 6.29167H12.7917" stroke="#FF5E14" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M5.29167 8.625L8.625 12.7917M5.29167 12.7917L8.625 8.625M11.125 16.125H2.79167C2.34964 16.125 1.92572 15.9494 1.61316 15.6368C1.30059 15.3243 1.125 14.9004 1.125 14.4583V2.79167C1.125 2.34964 1.30059 1.92572 1.61316 1.61316C1.92572 1.30059 2.34964 1.125 2.79167 1.125H8.625L12.7917 5.29167V14.4583C12.7917 14.9004 12.6161 15.3243 12.3035 15.6368C11.991 15.9494 11.567 16.125 11.125 16.125Z" stroke="#FF5E14" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Пример загрузки Excel файла
            </a>
            <button class="modal-upload__submit" data-modal-close>Отправить</button>
        </div>
        <div id="product-search-results"></div>
    </div>
    <?php
    return ob_get_clean();
}
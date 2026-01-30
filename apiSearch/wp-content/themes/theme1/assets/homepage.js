// Обработка клика по кнопке отправки модального окна
document.addEventListener('click', function(e) {
    if (e.target.matches('.modal-upload__submit')) {
        const oldTable = document.getElementById('getTable');
        if (oldTable) {
            oldTable.remove();
        }
    }
});

// Обработка формы квотирования
document.addEventListener('submit', function(e) {
    const form = e.target;

    if (form.matches('form.order-form') && form.querySelector('[name="action"][value="submit_component_request"]')) {
        e.preventDefault();
        
        const submitBtn = form.querySelector('#quoteSubmitBtn');
        if (!submitBtn) return;
        
        let actionUrl = form.getAttribute('action');
        if (actionUrl && actionUrl.charAt(0) !== '/') {
            actionUrl = '/' + actionUrl;
        }
        if (!actionUrl) {
            actionUrl = '/quote-handler.php';
        }

        fetch(actionUrl, {
            method: 'POST',
            body: new FormData(form)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Сервер вернул ошибку: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                submitBtn.style.display = 'none';
                submitBtn.insertAdjacentHTML('afterend', 
                    '<div class="success-message" style="color: #28a745; margin-top: 10px; font-weight: 500;">' +
                    'Запрос на квотирование успешно отправлен.' +
                    '</div>'
                );
            } else {
                alert('Ошибка: ' + (data.data || 'Неизвестная ошибка'));
            }
        })
        .catch(err => {
            console.error('Ошибка отправки:', err);
            alert('Не удалось отправить запрос. Проверьте соединение и попробуйте снова.');
        });
    }
});

// Копирование ссылки
document.addEventListener('click', function(e) {
    if (e.target.closest('.copy-url-link')) {
        e.preventDefault();
        const link = e.target.closest('.copy-url-link').getAttribute('data-link') ||
                     e.target.closest('.copy-url-link').href;
        if (link) {
            navigator.clipboard.writeText(link).then(function() {
                alert('Ссылка скопирована!');
            }).catch(function(err) {
                console.error('Не удалось скопировать:', err);
                alert('Ошибка: не удалось скопировать ссылку');
            });
        }
    }
});

// Основной код поиска (используем данные)
jQuery(document).ready(function($) {
    // === ИСПОЛЬЗУЕМ ДАННЫЕ ИЗ wp_localize_script ===
    const ajaxurl = myAjaxData.url;
    const nonce = myAjaxData.nonce;
    const themeUri = myAjaxData.themeUri;
    
    const resultsContainer = $('#product-search-results');
    const searchForm = $('#product-search-form');
    const singleSearchInput = $('#single-search-input');
    const singleSearchInputQuanity = $('#single-search-input-quanity');
    const listSearchInput = $('#list-search-input');
    const searchModeSingle = $('#search-mode-single');
    const searchModeList = $('#search-mode-list');
    const excelUpload = $('#excel-upload')[0];
    const uploadFilename = $('[data-upload-filename]');
    
    // Инициализация модального окна загрузки
    const modalShadow = $('[data-shadow]');
    const modalUpload = $('[data-modal-upload]');
    const uploadTrigger = $('[data-upload]');
    const modalClosers = $('[data-modal-close]');
    
    // Функция очистки ошибок
    function clearSearchErrors() {
        $('.search__error').remove();
        singleSearchInput.removeClass('search__input--invalid');
        singleSearchInputQuanity.removeClass('search__input--invalid');
        listSearchInput.removeClass('search__input--invalid');
        searchForm.removeClass('invalid');
    }
    
    // Открытие модального окна
    uploadTrigger.on('click', function(e) {
        e.preventDefault();
        modalShadow.addClass('visible');
        modalUpload.addClass('visible');
    });
    
    // Закрытие модального окна
    modalClosers.on('click', function() {
        modalShadow.removeClass('visible');
        modalUpload.removeClass('visible');
    });
    
    // Закрытие по нажатию Esc
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            modalShadow.removeClass('visible');
            modalUpload.removeClass('visible');
        }
    });
    
    // Логика переключения режимов поиска
    searchModeList.on('change', function() {
        if (searchModeList.is(':checked')) {
            clearSearchErrors();
            singleSearchInput.addClass('disable');
            singleSearchInputQuanity.addClass('disable');
            listSearchInput.removeClass('disable');
            listSearchInput.attr('placeholder', 'Поиск по артикулу\nC1005X6S1C105K:20\nC1005X6S1C105K:30\nC1005X6S1C105K:40\nC1005X6S1C105K:50');
            listSearchInput.focus();
        }
    });
    
    searchModeSingle.on('change', function() {
        if (searchModeSingle.is(':checked')) {
            clearSearchErrors();
            listSearchInput.addClass('disable'); 
            singleSearchInput.removeClass('disable');
            singleSearchInput.attr('placeholder', 'Поиск по артикулу');
            singleSearchInput.focus();
            
            singleSearchInputQuanity.removeClass('disable');
            singleSearchInputQuanity.attr('placeholder', 'Кол-во');
            singleSearchInputQuanity.focus();
        }
    });
    
    // Обработка перетаскивания файла
    const dropzone = $('[data-dropzone]')[0];
    if (dropzone) {
        dropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('drag-over');
        });
        
        dropzone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('drag-over');
        });
        
        dropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('drag-over');
            
            if (e.dataTransfer.files && e.dataTransfer.files.length) {
                handleFileDrop(e.dataTransfer.files);
            }
        });
    }
    
    function handleFileDrop(files) {
        if (files.length > 0) {
            const file = files[0];
            const isValid = validateFile(file);
            
            if (isValid) {
                excelUpload.files = files;
                if (excelUpload.files.length > 0) {
                    uploadFilename.text(excelUpload.files[0].name);
                }
            } else {
                alert('Неверный формат файла. Разрешены только .xls, .xlsx, .csv');
            }
        }
    }
    
    function validateFile(file) {
        const fileName = file.name.toLowerCase();
        const validExtensions = ['.xls', '.xlsx', '.csv'];
        return validExtensions.some(ext => fileName.endsWith(ext));
    }
    
    // Поиск по артикулу или по списку
    function search() {
        const isSingleMode = searchModeSingle.is(':checked');
        let query = '';
    
        if (isSingleMode) {
            const article = singleSearchInput.val().trim();
            const quantity = singleSearchInputQuanity.val().trim() || '1';
    
            if (!article) {
                alert('Введите артикул для поиска');
                return;
            }
    
            query = article + ':' + quantity;
            console.log('Сформированный запрос:', query);
    
        } else {
            query = listSearchInput.val().trim();
        }
    
        // Удаляем старую таблицу
        const getTable = $('#getTable');
        if (getTable) {
            getTable.remove();
        }
    
        // Показываем гифку загрузки
        resultsContainer.html(`
            <div class="search-loading">
                <img src="${themeUri}/images/loading.gif" alt="Загрузка..." style="width: 60px; height: 60px;">
            </div>
        `);
    
        if (!query) {
            alert('Введите артикул для поиска');
            return;
        }
    
        if (isSingleMode) {
            $.post(ajaxurl, {
                action: 'product_search_by_article',
                article: query,
                security: nonce
            }, function(response) {
                clearSearchErrors();
                handleSearchResponse(response);
            }).fail(handleSearchError);
    
        } else {
            $.post(ajaxurl, {
                action: 'product_search_by_list',
                articles: query,
                security: nonce
            }, function(response) {
                handleSearchResponse(response);
            }).fail(handleSearchError);
        }
    }
    
    function handleSearchResponse(response) {
        if (response.success) {
            resultsContainer.html(response.data);
            initQuantityInputs();
            initCartButtons();
        } else {
            resultsContainer.html('<div class="error">Ошибка: ' + response.data + '</div>');
        }
    }
    
    function handleSearchError(xhr, status, error) {
        resultsContainer.html('<div class="error">Ошибка запроса: ' + status + '</div>');
        console.error('AJAX error:', status, error);
    }
    
    // Обработка отправки формы с валидацией
    searchForm.on('submit', function(e) {
        e.preventDefault();
        if (validationForm()) {
            search();
        }
    });
    
    function showSearchError(field, message) {
        if (!field) return;
        
        $(field).next('.search__error').remove();
        const $error = $('<span class="search__error">' + message + '</span>');
        $(field).after($error);
        $(field).addClass('search__input--invalid');
        searchForm.addClass('invalid');
    }
    
    function validationForm() {
        clearSearchErrors();
    
        const errorText = "Поисковая строка может содержать только латинские буквы (a-z)(A-Z) или только кириллические буквы (а-я)(А-Я), цифры (0-9) и знаки.";
    
        const isSingleMode = searchModeSingle.is(':checked');
        const isListMode = searchModeList.is(':checked');
    
        if (!isSingleMode && !isListMode) {
            searchModeSingle.prop('checked', true);
        }
    
        function isValidLine(str) {
            if (!str) return false;
    
            const hasCyrillic = /[а-яА-ЯёЁ]/u.test(str);
            const hasLatin = /[a-zA-Z]/.test(str);
            return !(hasCyrillic && hasLatin);
        }
    
        if (isListMode) {
            const value = listSearchInput.val().trim();
            if (!value) {
                showSearchError(listSearchInput[0], "Поле не может быть пустым.");
                return false;
            }
    
            const lines = value
                .split(/\r?\n/)
                .map(line => line.trim())
                .filter(line => line.length > 0);
    
            for (const line of lines) {
                if (!isValidLine(line)) {
                    showSearchError(listSearchInput[0], `${errorText} Нарушение в одной из строк.`);
                    return false;
                }
            }
            return true;
    
        } else if (isSingleMode) {
            const value = singleSearchInput.val().trim();
            if (!value) {
                showSearchError(singleSearchInput[0], "Поле не может быть пустым.");
                return false;
            }
    
            if (!isValidLine(value)) {
                showSearchError(singleSearchInput[0], errorText);
                return false;
            }
            return true;
        }
    
        return false;
    }
    
    function handleFileUpload(file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'product_search_by_file');
        formData.append('security', nonce);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            
            beforeSend: function() {
                resultsContainer.html(`
                    <div class="search-loading">
                        <img src="${themeUri}/images/loading.gif" alt="Загрузка..." style="width: 60px; height: 60px;">
                    </div>
                `);
            },
            success: function(response) {
                handleSearchResponse(response);
                modalShadow.removeClass('visible');
                modalUpload.removeClass('visible');
            },
            error: function(xhr, status, error) {
                resultsContainer.html('<div class="error">Ошибка загрузки файла: ' + status + '</div>');
                console.error('File upload error:', status, error);
            }
        });
    }
    
    // Обработчик отправки файла из модального окна
    $('.modal-upload__submit').on('click', function() {
        if (excelUpload.files.length) {
            handleFileUpload(excelUpload.files[0]);
        } else {
            alert('Выберите файл для загрузки');
        }
    });
    
    // Обработчик выбора файла
    $(excelUpload).on('change', function() {
        if (this.files.length) {
            uploadFilename.text(this.files[0].name);
        } else {
            uploadFilename.text('Выбрать файл');
        }
    });
    
    // Функция инициализации сортировки таблицы
    document.addEventListener('click', function(e) {
        const header = e.target.closest('.sortable');
        if (!header) return;
    
        e.preventDefault();
    
        const table = header.closest('table');
        const column = header.dataset.sort;
        const arrowUp = header.querySelector('.arrow-up');
        const arrowDown = header.querySelector('.arrow-down');
    
        let order = 'asc';
        if (arrowUp && arrowUp.classList.contains('active')) {
            order = 'desc';
        } else if (arrowDown && arrowDown.classList.contains('active')) {
            order = 'none';
        }
    
        sortTable(table, column, order);
    
        table.querySelectorAll('.sort-arrows .active').forEach(el => el.classList.remove('active'));
        if (order === 'asc' && arrowUp) arrowUp.classList.add('active');
        if (order === 'desc' && arrowDown) arrowDown.classList.add('active');
    });
    
    // Инициализация полей ввода количества
    function initQuantityInputs() {
        $('.quantity-input').each(function() {
            const $input = $(this);
            const minQ = parseInt($input.attr('min')) || 1;
            const maxQ = parseInt($input.attr('max')) || 1;
            let quantity = parseInt($input.val()) || minQ;
    
            const $row = $input.closest('tr');
            const $priceRub = $row.find('.price-rub');
            const $totalRub = $row.find('.total-rub');
            const $folddivision = $row.find('.folddivision');
            const $available = $row.find('.available');
            const $minq = $row.find('.minq');
            const $addToCartBtn = $row.find('.add-to-cart-btn');
    
            let foldValue = 1;
            if ($folddivision.length) {
                const foldText = $folddivision.text().trim();
                const match = foldText.match(/Кратность:\s*(\d+)/i);
                if (match && match[1]) {
                    foldValue = parseInt(match[1]) || 1;
                }
            }
    
            const isMultiple = (quantity % foldValue === 0);
    
            $available.css('color', '');
            $folddivision.css('color', '');
            $minq.css('color', '');
            $addToCartBtn.removeClass('disabled').prop('disabled', false);
    
            if (!isMultiple) {
                $folddivision.css('color', 'red');
                $addToCartBtn.addClass('disabled').prop('disabled', true);
            }
            if (maxQ < quantity) {
                $available.css('color', 'red');
                $addToCartBtn.addClass('disabled').prop('disabled', true);
            }
            if (minQ > quantity) {
                $minq.css('color', 'red');
                $addToCartBtn.addClass('disabled').prop('disabled', true);
            }
    
            let price = parseFloat($priceRub.data('value')) || 0;
            const $multiItems = $priceRub.find('.multiPrice__item');
    
            if ($multiItems.length > 0) {
                let bestPrice = null;
                let bestItem = null;
    
                $multiItems.css('font-weight', '');
    
                $multiItems.each(function() {
                    const $item = $(this);
                    const qty = parseInt($item.find('[data-multi="quantity"]').attr('value'));
                    const itemPrice = parseFloat($item.find('[data-multi="price"]').attr('value'));
    
                    if (!isNaN(qty) && !isNaN(itemPrice) && qty <= quantity) {
                        bestPrice = itemPrice;
                        bestItem = $item;
                    }
                });
    
                if (bestItem) {
                    bestItem.css('font-weight', 'bold');
                    price = bestPrice;
                }
            }
    
            const total = price * quantity;
            $totalRub.data('value', total);
            $totalRub.text(total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' руб.');
        });
    
        $('.quantity-input').off('change input').on('change input', function() {
            const $input = $(this);
            const minQ = parseInt($input.attr('min')) || 1;
            const maxQ = parseInt($input.attr('max')) || 1;
            let quantity = parseInt($input.val()) || minQ;
    
            const $row = $input.closest('tr');
            const $priceRub = $row.find('.price-rub');
            const $totalRub = $row.find('.total-rub');
            const $folddivision = $row.find('.folddivision');
            const $available = $row.find('.available');
            const $minq = $row.find('.minq');
            const $addToCartBtn = $row.find('.add-to-cart-btn');
    
            let foldValue = 1;
            if ($folddivision.length) {
                const foldText = $folddivision.text().trim();
                const match = foldText.match(/Кратность:\s*(\d+)/i);
                if (match && match[1]) {
                    foldValue = parseInt(match[1]) || 1;
                }
            }
    
            const isMultiple = (quantity % foldValue === 0);
    
            $available.css('color', '');
            $folddivision.css('color', '');
            $minq.css('color', '');
            $addToCartBtn.removeClass('disabled').prop('disabled', false);
    
            if (!isMultiple) {
                $folddivision.css('color', 'red');
                $addToCartBtn.addClass('disabled').prop('disabled', true);
            }
            if (maxQ < quantity) {
                $available.css('color', 'red');
                $addToCartBtn.addClass('disabled').prop('disabled', true);
            }
            if (minQ > quantity) {
                $minq.css('color', 'red');
                $addToCartBtn.addClass('disabled').prop('disabled', true);
            }
    
            let price = parseFloat($priceRub.data('value')) || 0;
            const $multiItems = $priceRub.find('.multiPrice__item');
    
            if ($multiItems.length > 0) {
                let bestPrice = null;
                let bestItem = null;
    
                $multiItems.css('font-weight', '');
    
                $multiItems.each(function() {
                    const $item = $(this);
                    const qty = parseInt($item.find('[data-multi="quantity"]').attr('value'));
                    const itemPrice = parseFloat($item.find('[data-multi="price"]').attr('value'));
    
                    if (!isNaN(qty) && !isNaN(itemPrice) && qty <= quantity) {
                        bestPrice = itemPrice;
                        bestItem = $item;
                    }
                });
    
                if (bestItem) {
                    bestItem.css('font-weight', 'bold');
                    price = bestPrice;
                }
            }
    
            const total = price * quantity;
            $totalRub.data('value', total);
            $totalRub.text(total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' руб.');
        });
    }
    
    // Инициализация кнопок "Добавить в корзину"
    function initCartButtons() {
        $('.add-to-cart-btn').off('click').on('click', function() {
            const button = $(this);
            const $row = button.closest('tr');
    
            const article = button.data('article');
            const name = button.data('name');
            const brand = button.data('brand');
            const term = button.data('term');
            const donor = button.data('donor');
            const available = parseInt(button.data('available')) || 0;
    
            let quantity = 1;
            const quantityInput = $row.find('.quantity-input');
            if (quantityInput.length) {
                quantity = parseInt(quantityInput.val()) || 1;
            }
    
            let price = null;
            const $activePriceItem = $row.find('.multiPrice__item').filter(function() {
                const fontWeight = $(this).css('font-weight');
                return fontWeight === '700' || fontWeight === 'bold';
            });
    
            if ($activePriceItem.length) {
                const priceStr = $activePriceItem.find('[data-multi="price"]').attr('value');
                price = parseFloat(priceStr);
            }
    
            if (isNaN(price)) {
                const priceText = $row.find('.price-rub').text();
                const match = priceText.match(/[\d\s,]+(?=\s*руб)/);
                if (match) {
                    price = parseFloat(match[0].replace(/\s/g, '').replace(',', '.'));
                }
            }
    
            if (isNaN(price) || price <= 0) {
                alert('Не удалось определить цену. Попробуйте обновить страницу.');
                return;
            }
    
            let cart = [];
            const cartCookie = document.cookie
                .split('; ')
                .find(row => row.startsWith('simple_cart='));
            if (cartCookie) {
                try {
                    cart = JSON.parse(decodeURIComponent(cartCookie.split('=')[1]));
                } catch (e) {
                    cart = [];
                }
            }
    
            let alreadyInCart = 0;
            cart.forEach(item => {
                if (item.article === article && item.brand === brand) {
                    alreadyInCart += item.quantity;
                }
            });
    
            if (alreadyInCart + quantity > available) {
                alert(`Нельзя добавить больше ${available} шт. этого товара. Уже добавлено: ${alreadyInCart} шт.`);
                return;
            }
    
            const total = price * quantity;
            cart.push({
                article: article,
                donor: donor,
                name: name,
                brand: brand,
                term: term,
                price: price,
                quantity: quantity,
                total: total
            });
    
            const positionsCount = cart.length;
            const expires = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toUTCString();
            document.cookie = `simple_cart=${encodeURIComponent(JSON.stringify(cart))}; expires=${expires}; path=/; SameSite=Lax`;
            document.cookie = `cart_count=${positionsCount}; expires=${expires}; path=/; SameSite=Lax`;
    
            const scoreEl = document.querySelector('.product_score');
            if (scoreEl) scoreEl.textContent = positionsCount;
    
            const formatNumber = (num) => num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    
            alert(`Вы выбрали:\n\nНаименование: ${name}\nАртикул: ${article}\nПроизводитель: ${brand}\nКоличество: ${quantity} шт.\nЦена за шт.: ${formatNumber(price)} руб.\nОбщая сумма: ${formatNumber(total)} руб.\nСрок: ${term}\nПоставщик: ${donor}`);
    
            const cartDisplayText = positionsCount > 0
                ? `<div class="product_score header">${positionsCount}</div>`
                : 'Пустая';
    
            const headerCountEl = document.querySelector('.cart-item .cart-item_descr:last-child');
            if (headerCountEl) {
                headerCountEl.innerHTML = cartDisplayText;
            }
        });
    }
    
    function sortTable(table, column, order) {
        const tbody = table.querySelector('tbody');
        if (!tbody) return;
    
        const rows = Array.from(tbody.querySelectorAll('tr'));
    
        if (order !== 'none') {
            rows.sort((a, b) => {
                let aVal, bVal;
    
                if (column === 'term') {
                    aVal = a.querySelector('.term')?.dataset.value || '';
                    bVal = b.querySelector('.term')?.dataset.value || '';
                    const parse = t => {
                        if (!t || t === 'Под заказ') return 999;
                        const n = t.match(/\d+/);
                        return n ? +n[0] : 500;
                    };
                    aVal = parse(aVal);
                    bVal = parse(bVal);
                }
                else if (column === 'price_rub' || column === 'total_rub') {
                    const cls = column.replace('_', '-');
                    aVal = parseFloat(a.querySelector(`.${cls}`)?.dataset.value) || 0;
                    bVal = parseFloat(b.querySelector(`.${cls}`)?.dataset.value) || 0;
                }
                else {
                    aVal = (a.querySelector(`.${column}`)?.textContent || '').trim().toLowerCase();
                    bVal = (b.querySelector(`.${column}`)?.textContent || '').trim().toLowerCase();
                }
    
                return order === 'asc' ? (aVal > bVal ? 1 : -1) : (aVal < bVal ? 1 : -1);
            });
        }
    
        tbody.innerHTML = '';
        rows.forEach(row => tbody.appendChild(row));
    }
    
    // Инициализация при загрузке страницы
    initCartButtons();
    initQuantityInputs();
});

// Экспорт в Excel
document.addEventListener('click', function(e) {
    if (!e.target.matches('#exportToExcel')) return;
    const table = document.querySelector('.product-search-table');
    if (!table) return;

    const headers = Array.from(table.querySelectorAll('thead th'))
        .slice(0, -1)
        .map(th => th.textContent.trim());

    const data = [];
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        let multiItems = null;
        
        tr.querySelectorAll('td').forEach((td, index) => {
            if (index === headers.length) return;

            if (td.classList.contains('price-rub')) {
                multiItems = td.querySelectorAll('.multiPrice__item');
                
                if (multiItems.length > 0) {
                    const prices = Array.from(multiItems).map(item => {
                        const priceEl = item.querySelector('[data-multi="price"]');
                        return priceEl?.getAttribute('value')?.replace('.', ',') || '';
                    }).filter(Boolean);
                    
                    row.push(prices.join('\n'));
                } else {
                    const val = td.getAttribute('data-value');
                    row.push(val ? val.replace('.', ',') : td.textContent.trim());
                }
            }
            else if (td.classList.contains('total-rub') && multiItems) {
                const sums = Array.from(multiItems).map(item => {
                    const qtyEl = item.querySelector('[data-multi="quantity"]');
                    const priceEl = item.querySelector('[data-multi="price"]');
                    
                    const qty = qtyEl?.getAttribute('value') || '';
                    const price = priceEl?.getAttribute('value') || '';
                    
                    if (qty && price) {
                        const total = (parseFloat(qty) * parseFloat(price)).toFixed(2).replace('.', ',');
                        return `x ${qty} = ${total}`;
                    } 
                    return '';
                }).filter(Boolean);
                
                row.push(sums.join('\n'));
            }
            else if (td.classList.contains('quantity')) {
                const input = td.querySelector('input.quantity-input');
                row.push(input ? input.value : '');
            }
            else {
                row.push(td.textContent.trim());
            }
        });

        data.push(row);
    });

    const ws = XLSX.utils.aoa_to_sheet([headers, ...data]);

    const range = XLSX.utils.decode_range(ws['!ref']);
    for (let R = range.s.r; R <= range.e.r; ++R) {
        for (let C = range.s.c; C <= range.e.c; ++C) {
            const cellRef = XLSX.utils.encode_cell({r: R, c: C});
            if (ws[cellRef] && ws[cellRef].v && typeof ws[cellRef].v === 'string' && ws[cellRef].v.includes('\n')) {
                if (!ws[cellRef].s) ws[cellRef].s = {};
                ws[cellRef].s.alignment = { wrapText: true };
            }
        }
    }

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Товары");
    XLSX.writeFile(wb, "megaatom_товары.xlsx");
});
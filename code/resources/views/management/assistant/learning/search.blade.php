<!-- search.blade.php -->
<div class="mb-4">
    <button id="vectorSearchBtn" class="btn btn-primary btn-lg w-100">
        <i class="fas fa-search"></i> Векторный поиск
    </button>
</div>

<!-- Модальное окно для поиска -->
<div class="modal fade" id="vectorSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Векторный поиск</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="searchText" class="form-label">Введите текст для поиска:</label>
                    <textarea id="searchText" class="form-control" rows="3" placeholder="Введите текст..."></textarea>
                </div>

                <div class="mb-3">
                    <label for="searchType" class="form-label">Тип поиска:</label>
                    <select id="searchType" class="form-select">
                        <option value="async">Асинхронный (для больших запросов)</option>
                        <option value="sync">Синхронный (быстрый, для небольших запросов)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="neighborsCount" class="form-label">Количество ближайших точек:</label>
                    <input type="number" id="neighborsCount" class="form-control" value="5" min="1" max="100">
                </div>

                <button id="searchExecuteBtn" class="btn btn-success mb-3">
                    <i class="fas fa-search"></i> Найти
                </button>

                <hr />

                <h6>Результаты:</h6>
                <div id="searchResults">
                    <p class="text-muted">Здесь будут отображены ближайшие точки.</p>
                </div>

                <!-- Прогресс-бар для асинхронного поиска -->
                <div id="searchProgress" class="progress mt-3" style="display: none; height: 20px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" style="width: 0%"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const modal = new bootstrap.Modal('#vectorSearchModal');
    let currentSearchId = null; // Изменили на search_id
    let statusCheckInterval = null;

    $('#vectorSearchBtn').on('click', function() {
        modal.show();
        $('#searchResults').html('<p class="text-muted">Здесь будут отображены ближайшие точки.</p>');
        $('#searchText').val('');
        $('#neighborsCount').val(5);
        $('#searchProgress').hide();
        clearInterval(statusCheckInterval);
        currentSearchId = null; // Изменили на search_id
    });

    $('#searchExecuteBtn').on('click', function() {
        const text = $('#searchText').val().trim();
        const k = parseInt($('#neighborsCount').val()) || 5;
        const searchType = $('#searchType').val();

        if (!text) {
            alert('Введите текст для поиска');
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Поиск...');

        if (searchType === 'sync') {
            // Синхронный поиск
            performSyncSearch(text, k, btn);
        } else {
            // Асинхронный поиск
            performAsyncSearch(text, k, btn);
        }
    });

    function performSyncSearch(text, k, btn) {
        $('#searchResults').html('<p class="text-muted">Выполняется синхронный поиск...</p>');

        $.ajax({
            url: '{{ route("m.assistant.learning.store") }}',
            type: 'POST',
            data: JSON.stringify({
                command: 'direct_search',
                text: text,
                k: k
            }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function(data) {
                if (data.success) {
                    displaySearchResults(data);
                } else {
                    $('#searchResults').html('<p class="text-danger">Ошибка: ' + data.message + '</p>');
                }
            },
            error: function(xhr) {
                $('#searchResults').html('<p class="text-danger">Ошибка сети: ' + xhr.statusText + '</p>');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-search"></i> Найти');
            }
        });
    }

    function performAsyncSearch(text, k, btn) {
        $('#searchResults').html('<p class="text-muted">Запуск асинхронного поиска...</p>');
        $('#searchProgress').show().find('.progress-bar').css('width', '10%');

        // Запускаем асинхронный поиск
        $.ajax({
            url: '{{ route("m.assistant.learning.store") }}',
            type: 'POST',
            data: JSON.stringify({
                command: 'vector_search',
                text: text,
                k: k
            }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function(data) {
                if (data.success) {
                    currentSearchId = data.search_id; // Используем search_id вместо cache_key
                    $('#searchProgress').find('.progress-bar').css('width', '30%');
                    $('#searchResults').html('<p class="text-info">Поиск запущен в фоне. Ожидаем результаты...</p>');
                    
                    // Запускаем проверку статуса каждые 2 секунды
                    statusCheckInterval = setInterval(() => {
                        checkSearchStatus(btn);
                    }, 2000);
                } else {
                    $('#searchResults').html('<p class="text-danger">Ошибка: ' + data.message + '</p>');
                    btn.prop('disabled', false).html('<i class="fas fa-search"></i> Найти');
                }
            },
            error: function(xhr) {
                $('#searchResults').html('<p class="text-danger">Ошибка сети: ' + xhr.statusText + '</p>');
                btn.prop('disabled', false).html('<i class="fas fa-search"></i> Найти');
            }
        });
    }

    function checkSearchStatus(btn) {
        if (!currentSearchId) return;

        $.ajax({
            url: '{{ route("m.assistant.learning.store") }}',
            type: 'POST',
            data: JSON.stringify({
                command: 'search_status',
                search_id: currentSearchId // Используем search_id вместо cache_key
            }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function(data) {
                if (data.success) {
                    // Обновляем прогресс в зависимости от статуса
                    switch(data.status) {
                        case 'pending':
                            $('#searchProgress').find('.progress-bar').css('width', '40%');
                            $('#searchResults').html('<p class="text-info">Поиск в очереди...</p>');
                            break;
                        case 'processing':
                            $('#searchProgress').find('.progress-bar').css('width', '60%');
                            $('#searchResults').html('<p class="text-info">Поиск выполняется...</p>');
                            break;
                        case 'completed':
                            $('#searchProgress').find('.progress-bar').css('width', '100%');
                            displaySearchResults(data);
                            clearInterval(statusCheckInterval);
                            btn.prop('disabled', false).html('<i class="fas fa-search"></i> Найти');
                            break;
                        case 'error':
                            $('#searchProgress').find('.progress-bar').css('width', '100%');
                            $('#searchResults').html('<p class="text-danger">Ошибка: ' + data.message + '</p>');
                            clearInterval(statusCheckInterval);
                            btn.prop('disabled', false).html('<i class="fas fa-search"></i> Найти');
                            break;
                        default:
                            $('#searchResults').html('<p class="text-warning">Неизвестный статус: ' + data.status + '</p>');
                    }
                } else {
                    $('#searchResults').html('<p class="text-danger">Ошибка: ' + data.message + '</p>');
                    clearInterval(statusCheckInterval);
                    btn.prop('disabled', false).html('<i class="fas fa-search"></i> Найти');
                }
            },
            error: function(xhr) {
                $('#searchResults').html('<p class="text-danger">Ошибка проверки статуса: ' + xhr.statusText + '</p>');
                clearInterval(statusCheckInterval);
                btn.prop('disabled', false).html('<i class="fas fa-search"></i> Найти');
            }
        });
    }

    function displaySearchResults(data) {
        if (data.results && data.results.length > 0) {
            let html = `<div class="alert alert-success">
                <strong>Найдено результатов: ${data.results_count}</strong><br>
                <small>Запрос: "${data.query_text}"</small>`;
            
            // Добавляем информацию о времени выполнения если есть
            if (data.processing_time) {
                html += `<br><small>Время выполнения: ${data.processing_time} сек.</small>`;
            }
            
            html += `</div>`;
            
            data.results.forEach((result, index) => {
                html += `<div class="card mb-2">
                    <div class="card-body">
                        <h6 class="card-title">Результат #${index + 1}</h6>
                        <p class="card-text">${result.payload?.text || 'Нет текста'}</p>
                        <small class="text-muted">Score: ${result.score?.toFixed(4) || 'N/A'}</small>
                    </div>
                </div>`;
            });
            
            $('#searchResults').html(html);
        } else {
            $('#searchResults').html('<p class="text-warning">По вашему запросу ничего не найдено.</p>');
        }
        
        $('#searchProgress').hide();
    }

    // Очистка при закрытии модального окна
    $('#vectorSearchModal').on('hidden.bs.modal', function() {
        clearInterval(statusCheckInterval);
        currentSearchId = null;
    });

    // Решение проблемы с aria-hidden (добавляем обработчик для правильного закрытия)
    $('#vectorSearchModal').on('show.bs.modal', function() {
        // Убедимся, что все элементы с фокусом внутри модального окна
        $(this).find('.btn-close').focus();
    });

    $('#vectorSearchModal').on('hide.bs.modal', function() {
        // Возвращаем фокус на кнопку открытия модального окна
        $('#vectorSearchBtn').focus();
    });
});
</script>
@extends('template.template',[
	'title'=>'Управление браузером',
])
@push('sidebar') @include('management.sidebar') @endpush
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-globe"></i> Браузерный контейнер
                    </h3>
                    <div class="card-tools">
                        <span class="badge badge-{{ $serviceInfo['is_configured'] ? 'success' : 'danger' }}">
                            {{ $serviceInfo['is_configured'] ? 'Подключен' : 'Отключен' }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Статус сервиса -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-info"><i class="fas fa-server"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Хост браузера</span>
                                    <span class="info-box-number">{{ $serviceInfo['host'] ?? 'Не настроен' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-box">
                                <span class="info-box-icon bg-{{ $serviceInfo['is_configured'] ? 'success' : 'danger' }}">
                                    <i class="fas fa-plug"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Статус</span>
                                    <span class="info-box-number">
                                        {{ $serviceInfo['is_configured'] ? 'Активен' : 'Неактивен' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Быстрые действия -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5>Быстрые действия</h5>
                            <div class="btn-group mb-2">
                                <button type="button" class="btn btn-primary" onclick="testConnection()">
                                    <i class="fas fa-bolt"></i> Проверить подключение
                                </button>
                                <a href="{{ route('m.assistant.browser.debug') }}" class="btn btn-info">
                                    <i class="fas fa-bug"></i> Страница отладки
                                </a>
                                <a href="{{ route('m.assistant.browser.create') }}" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Новый сценарий
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Форма быстрого поиска -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Быстрый поиск</h5>
                                </div>
                                <div class="card-body">
                                    <form id="quickSearchForm">
                                        @csrf
                                        <input type="hidden" name="command" value="quick_search">
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label>Поисковый запрос</label>
                                                <input type="text" name="query" class="form-control" 
                                                       value="Илон Маск" required>
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label>Поисковая система</label>
                                                <select name="engine" class="form-control">
                                                    <option value="google">Google</option>
                                                    <option value="yandex">Yandex</option>
                                                    <option value="bing">Bing</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-2">
                                                <label>&nbsp;</label>
                                                <button type="submit" class="btn btn-primary btn-block">
                                                    <i class="fas fa-search"></i> Найти
                                                </button>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-10">
                                                <label>CSS-селектор для извлечения (опционально)</label>
                                                <input type="text" name="extract_selector" 
                                                       class="form-control" placeholder="h3, .title, etc.">
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Быстрый переход</h5>
                                </div>
                                <div class="card-body">
                                    <form id="browseUrlForm">
                                        @csrf
                                        <input type="hidden" name="command" value="browse_url">
                                        <div class="form-group">
                                            <label>URL адрес</label>
                                            <input type="url" name="url" class="form-control" 
                                                   value="https://google.com" required>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" name="screenshot" class="form-check-input" checked>
                                            <label class="form-check-label">Сделать скриншот</label>
                                        </div>
                                        <button type="submit" class="btn btn-success btn-block mt-2">
                                            <i class="fas fa-external-link-alt"></i> Перейти
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Результаты выполнения -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">Результаты выполнения</h5>
                                </div>
                                <div class="card-body">
                                    <div id="resultsContainer">
                                        @if(session('execution_result'))
                                            @include('management.assistant.browser.partials.results', ['result' => session('execution_result')])
                                        @else
                                            <div class="text-center text-muted py-4">
                                                <i class="fas fa-arrow-up fa-2x mb-2"></i>
                                                <p>Выполните команду чтобы увидеть результаты</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- История команд -->
                    @if(!empty($recentCommands))
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">История команд (последние {{ count($recentCommands) }})</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Время</th>
                                                    <th>Действия</th>
                                                    <th>Статус</th>
                                                    <th>Детали</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach(array_reverse($recentCommands) as $index => $historyItem)
                                                <tr>
                                                    <td>{{ \Carbon\Carbon::parse($historyItem['timestamp'])->format('H:i:s') }}</td>
                                                    <td>
                                                        @foreach($historyItem['commands'] as $cmd)
                                                            <span class="badge badge-light">{{ $cmd['action'] }}</span>
                                                        @endforeach
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-{{ $historyItem['result']['status'] === 'success' ? 'success' : 'danger' }}">
                                                            {{ $historyItem['result']['status'] }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-info" 
                                                                onclick="showHistoryResult({{ $index }})">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для результатов -->
<div class="modal fade" id="resultModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Результаты выполнения</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="modalResultContent">
                <!-- Контент будет загружен через AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для скриншотов -->
<div class="modal fade" id="screenshotModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Скриншот</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <img id="screenshotImage" src="" class="img-fluid" alt="Скриншот">
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Быстрый поиск
document.getElementById('quickSearchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    executeCommand(new FormData(this));
});

// Переход по URL
document.getElementById('browseUrlForm').addEventListener('submit', function(e) {
    e.preventDefault();
    executeCommand(new FormData(this));
});

// Тестирование подключения
function testConnection() {
    showLoading('Проверка подключения...');
    
    fetch('{{ route("m.assistant.browser.health") }}')
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.status === 'healthy') {
                showAlert('success', 'Подключение активно! Сервис работает нормально.');
            } else {
                showAlert('error', 'Проблемы с подключением: ' + JSON.stringify(data));
            }
        })
        .catch(error => {
            hideLoading();
            showAlert('error', 'Ошибка проверки подключения: ' + error);
        });
}

// Выполнение команды
function executeCommand(formData) {
    showLoading('Выполнение команды...');
    
    fetch('{{ route("m.assistant.browser.store") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        hideLoading();
        displayResults(data);
    })
    .catch(error => {
        hideLoading();
        showAlert('error', 'Ошибка выполнения: ' + error.message);
    });
}

// Отображение результатов
function displayResults(result) {
    fetch('{{ route("m.assistant.browser.partial.results") }}', {
        method: 'POST',
        body: JSON.stringify({ result: result }),
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error ' + response.status);
        }
        return response.text();
    })
    .then(html => {
        document.getElementById('resultsContainer').innerHTML = html;
    })
    .catch(error => {
        console.error('Error loading results:', error);
        document.getElementById('resultsContainer').innerHTML = 
            '<div class="alert alert-danger">Ошибка загрузки результатов: ' + error.message + '</div>';
    });
}

// Показать скриншот
function showScreenshot(base64Data) {
    document.getElementById('screenshotImage').src = base64Data;
    $('#screenshotModal').modal('show');
}

// Показать скриншот
function showScreenshot(base64Data) {
    document.getElementById('screenshotImage').src = base64Data;
    $('#screenshotModal').modal('show');
}

// Утилиты
function showLoading(message) {
    // Создаем или используем существующий индикатор загрузки
    let loader = document.getElementById('loadingIndicator');
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'loadingIndicator';
        loader.className = 'alert alert-info text-center';
        loader.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999;';
        document.body.appendChild(loader);
    }
    loader.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + message;
    loader.style.display = 'block';
}

function hideLoading() {
    const loader = document.getElementById('loadingIndicator');
    if (loader) {
        loader.style.display = 'none';
    }
}

function showAlert(type, message) {
    // Используем Bootstrap toast или создаем простой alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    `;
    
    // Добавляем в начало контента
    const content = document.querySelector('.container-fluid');
    content.insertBefore(alertDiv, content.firstChild);
    
    // Автоматически скрываем через 5 секунд
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    console.log('Browser Controller initialized');
    
    // Безопасная инициализация обработчиков форм
    const quickSearchForm = document.getElementById('quickSearchForm');
    const browseUrlForm = document.getElementById('browseUrlForm');
    
    if (quickSearchForm) {
        quickSearchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            executeCommand(new FormData(this));
        });
    } else {
        console.warn('Element #quickSearchForm not found');
    }
    
    if (browseUrlForm) {
        browseUrlForm.addEventListener('submit', function(e) {
            e.preventDefault();
            executeCommand(new FormData(this));
        });
    } else {
        console.warn('Element #browseUrlForm not found');
    }
});

// Показать результат из истории
function showHistoryResult(index) {
    showLoading('Загрузка результата...');
    
    // В реальной реализации здесь был бы запрос к серверу
    // за конкретным результатом по индексу
    setTimeout(() => {
        hideLoading();
        showAlert('info', 'Функция просмотра детальной истории в разработке');
    }, 1000);
}
</script>
@endpush
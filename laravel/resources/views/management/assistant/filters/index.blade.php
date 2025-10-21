@extends('template.template', [
    'title' => 'Фильтры'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
<style>
    .filter-card {
        transition: all 0.3s ease;
        border: 1px solid #dee2e6;
    }
    .filter-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        border-color: #007bff;
    }
    .filter-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    .filter-type-badge {
        font-size: 0.7em;
        padding: 0.25em 0.6em;
    }
    .settings-section {
        border-left: 4px solid #007bff;
        background: #f8f9fa;
    }
    .parameters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1rem;
    }
    .parameter-item {
        margin-bottom: 1rem;
        padding: 1rem;
        background: white;
        border-radius: 0.375rem;
        border: 1px solid #dee2e6;
    }
    .debug-mode-section {
        border-left: 4px solid #ffc107;
        background: #fffbf0;
    }
</style>
@endpush

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Управление фильтрами сообщений</h4>
        <p class="text-muted mb-0">Настройка цепочки обработки входящих сообщений</p>
    </div>
    <a href="{{ route('m.assistant.filters.create') }}" class="btn btn-primary">
        <i class="fas fa-plus-circle me-2"></i> Создать фильтр
    </a>
</div>

<!-- Карточки фильтров -->
<div class="row" id="filters-container">
    @foreach($filters as $filter)
    @php
        $handlerClass = $filter->propertyById(108)->pivot->value ?? null;
        $hasParameters = false;
        $parametersCount = 0;
        $filterName = $filter->propertyById(1)->pivot->value ?? 'Без названия';
        $filterDescription = $filter->propertyById(109)->pivot->value ?? '';
        $filterType = $filter->propertyById(107)->pivot->value ?? 'handler';
        $filterOrder = $filter->propertyById(112)->pivot->value ?? 0;
        $isActive = filter_var($filter->propertyById(116)->pivot->value ?? false, FILTER_VALIDATE_BOOLEAN);
        
        // Получаем параметры для отображения статуса отладки
        $debugMode = false;
        $parametersProperty = $filter->propertyById(102);
        if ($parametersProperty) {
            $parameters = json_decode($parametersProperty->pivot->value, true) ?? [];
            $debugMode = filter_var($parameters['debug_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        
        // УПРОЩЕННАЯ ПРОВЕРКА КЛАССА - убираем строгую проверку
        $classExists = true; // Предполагаем, что класс существует
        $classNameForDisplay = 'Не указан';
        
        if ($handlerClass && !empty(trim($handlerClass))) {
            // Убираем часть после @ если есть
            $cleanHandlerClass = explode('@', $handlerClass)[0];
            $classNameForDisplay = class_basename($cleanHandlerClass);
            
            // Простая проверка без создания экземпляра
            try {
                // Используем рефлексию для проверки класса
                $reflection = new ReflectionClass($cleanHandlerClass);
                if ($reflection->hasMethod('getParametersStructure')) {
                    $hasParameters = true;
                    // Получаем структуру параметров без создания экземпляра
                    $defaultParameters = [
                        'debug_enabled' => ['type' => 'boolean'],
                        'debug_recipients' => ['type' => 'text']
                    ];
                    $parametersCount = count($defaultParameters);
                }
            } catch (Throwable $e) {
                // Если класс не найден, все равно показываем его
                $classExists = false;
                $hasParameters = false;
                $parametersCount = 0;
            }
        }
        
        $typeColors = [
            'prompt' => 'bg-info',
            'handler' => 'bg-primary', 
            'unknown' => 'bg-secondary'
        ];
        $typeLabels = [
            'prompt' => 'Промт',
            'handler' => 'Обработчик',
            'unknown' => 'Неизвестно'
        ];
    @endphp
    
    <div class="col-lg-6 col-xl-4 mb-4" data-order="{{ $filterOrder }}" data-id="{{ $filter->id }}">
        <div class="card filter-card h-100">
            <div class="card-header filter-header py-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1 text-truncate" title="{{ $filterName }}">
                            <i class="fas fa-filter me-2"></i>{{ $filterName }}
                        </h6>
                        <div class="d-flex align-items-center mt-2">
                            <span class="badge {{ $typeColors[$filterType] ?? 'bg-secondary' }} filter-type-badge me-2">
                                {{ $typeLabels[$filterType] ?? $filterType }}
                            </span>
                            <span class="badge bg-light text-dark filter-type-badge">
                                Порядок: {{ $filterOrder }}
                            </span>
                            @if($debugMode)
                            <span class="badge bg-warning text-dark filter-type-badge ms-2" title="Режим отладки включен">
                                <i class="fas fa-bug me-1"></i>Debug
                            </span>
                            @endif
                        </div>
                    </div>
                    <div class="form-check form-switch ms-2">
                        <input type="checkbox" 
                               class="form-check-input status-switch"
                               data-filter-id="{{ $filter->id }}"
                               {{ $isActive ? 'checked' : '' }}
                               id="switch-{{ $filter->id }}">
                        <label class="form-check-label" for="switch-{{ $filter->id }}"></label>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                @if($filterDescription)
                <p class="card-text small text-muted mb-3">{{ $filterDescription }}</p>
                @endif
                
                <div class="mb-3">
                    <small class="text-muted">Обработчик:</small>
                    <div class="small text-truncate" title="{{ $handlerClass ?? 'Не указан' }}">
                        <i class="fas fa-code me-1"></i>
                        {{ $classNameForDisplay }}
                        @if($handlerClass && strpos($handlerClass, '@') !== false)
                            <span class="text-muted" title="Метод: {{ explode('@', $handlerClass)[1] ?? 'handle' }}">
                                @{{ explode('@', $handlerClass)[1] ?? 'handle' }}
                            </span>
                        @endif
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        @if($hasParameters)
                        <span class="badge bg-light text-dark border parameters-badge">
                            <i class="fas fa-sliders-h me-1"></i>{{ $parametersCount }} параметров
                        </span>
                        @else
                        <span class="badge bg-light text-muted border parameters-badge">
                            <i class="fas fa-sliders-h me-1"></i>Нет параметров
                        </span>
                        @endif
                    </div>
                    
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary settings-btn"
                                data-filter-id="{{ $filter->id }}"
                                data-bs-toggle="modal" 
                                data-bs-target="#settingsModal"
                                onclick="loadFilterSettings({{ $filter->id }}, '{{ addslashes($filterName) }}')">
                            <i class="fas fa-cog me-1"></i>Настроить
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
    
    @if($filters->isEmpty())
    <div class="col-12">
        <div class="card text-center py-5">
            <div class="card-body">
                <i class="fas fa-filter fa-4x text-muted mb-4"></i>
                <h5 class="text-muted">Нет созданных фильтров</h5>
                <p class="text-muted mb-4">Создайте первый фильтр для обработки сообщений</p>
                <a href="{{ route('m.assistant.filters.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i> Создать первый фильтр
                </a>
            </div>
        </div>
    </div>
    @endif
</div>

<!-- Модальное окно настроек -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="settingsModalLabel">
                    <i class="fas fa-cog me-2"></i>Настройки фильтра
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="settingsModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                    <p class="mt-3 text-muted">Загрузка настроек фильтра...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Закрыть
                </button>
                <button type="button" class="btn btn-success" onclick="saveSimpleSettings()">
                    <i class="fas fa-save me-2"></i>Сохранить настройки
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<script>
let currentFilterId = null;
let currentParametersStructure = {};

// Загрузка настроек фильтра
function loadFilterSettings(filterId, filterName) {
    currentFilterId = filterId;
    
    $('#settingsModalBody').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
            <p class="mt-3 text-muted">Загрузка настроек фильтра...</p>
        </div>
    `);

    // Используем FormData для запроса
    const fd = new FormData();
    fd.append('_token', '{{ csrf_token() }}');
    fd.append('_method', 'PUT');
    fd.append('command', 'get-settings');

    $.ajax({
        url: `/management/assistant/filters/${filterId}`,
        type: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                renderSimpleSettingsForm(filterId, response.filter);
            } else {
                $('#settingsModalBody').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Ошибка загрузки настроек: ${response.error}
                    </div>
                `);
            }
        },
        error: function(xhr, status, error) {
            $('#settingsModalBody').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Ошибка загрузки настроек: ${error}
                </div>
            `);
        }
    });
}

// Рендер формы простых настроек
function renderSimpleSettingsForm(filterId, filterData) {
    const { basic_settings, parameters_structure, parameters, has_handler } = filterData;
    
    // Сохраняем структуру параметров для использования при сохранении
    currentParametersStructure = parameters_structure;
    
    // Определяем текущее значение debug_mode
    const debugMode = parameters.debug_enabled || false;
    
    let html = `
        <div id="filterSettingsContent">
            <!-- Основные настройки -->
            <div class="settings-section p-3 mb-4 rounded">
                <h6 class="mb-3"><i class="fas fa-cogs me-2"></i>Основные настройки</h6>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Название фильтра</label>
                        <input type="text" class="form-control" id="filterName" value="${basic_settings.name || ''}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Тип фильтра</label>
                        <select class="form-select" id="filterType">
                            <option value="handler" ${basic_settings.type === 'handler' ? 'selected' : ''}>Обработчик</option>
                            <option value="prompt" ${basic_settings.type === 'prompt' ? 'selected' : ''}>Промт</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Класс обработчика</label>
                        <input type="text" class="form-control" id="filterHandler" value="${basic_settings.handler || ''}" 
                            placeholder="App\Filters\ExampleFilter">
                        <div class="form-text text-muted">
                            <i class="fas fa-info-circle me-1"></i>Формат: App\Filters\ClassName@method
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Описание</label>
                        <textarea class="form-control" id="filterDescription" rows="2">${basic_settings.description || ''}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Порядок выполнения</label>
                        <input type="number" class="form-control" id="filterOrder" value="${basic_settings.order || 0}" min="0">
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mt-4 pt-2">
                            <input type="checkbox" class="form-check-input" id="filterEnabled" value="1" 
                                   ${basic_settings.enabled ? 'checked' : ''}>
                            <label class="form-check-label">Активирован</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mt-4 pt-2">
                            <input type="checkbox" class="form-check-input" id="filterDebugMode" value="1" 
                                   ${debugMode ? 'checked' : ''}>
                            <label class="form-check-label text-warning">
                                <i class="fas fa-bug me-1"></i>Режим отладки
                            </label>
                        </div>
                    </div>
                </div>
            </div>
    `;
    
    // Параметры фильтра
    if (Object.keys(parameters_structure).length > 0) {
        html += `
            <div class="settings-section p-3 mb-4 rounded">
                <h6 class="mb-3"><i class="fas fa-sliders-h me-2"></i>Параметры фильтра</h6>
                <div class="parameters-grid">
                    ${renderSimpleParameters(parameters_structure, parameters)}
                </div>
            </div>
        `;
    } else {
        html += `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Этот фильтр не требует дополнительных параметров
            </div>
        `;
    }
    
    html += `</div>`;
    
    $('#settingsModalBody').html(html);
}

// Рендер простого поля параметра
function renderSimpleParameterField(paramKey, config, currentValue) {
    const fieldId = `param_${paramKey}`;
    
    switch (config.type) {
        case 'boolean':
            const isChecked = Boolean(currentValue);
            return `
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="${fieldId}" 
                           value="1" ${isChecked ? 'checked' : ''}>
                    <label class="form-check-label">${config.label_checked || 'Включено'}</label>
                </div>
            `;
            
        case 'number':
            return `
                <input type="number" class="form-control" id="${fieldId}"
                       value="${currentValue}" 
                       ${config.min !== undefined ? `min="${config.min}"` : ''}
                       ${config.max !== undefined ? `max="${config.max}"` : ''}
                       ${config.step !== undefined ? `step="${config.step}"` : ''}
                       ${config.required ? 'required' : ''}>
            `;
            
        case 'textarea':
            const rows = config.rows || 3;
            return `
                <textarea class="form-control" id="${fieldId}" rows="${rows}"
                          ${config.required ? 'required' : ''}
                          placeholder="${config.placeholder || ''}">${currentValue}</textarea>
            `;
            
        case 'select':
            let options = '';
            Object.keys(config.options || {}).forEach(optionValue => {
                const optionLabel = config.options[optionValue];
                const isSelected = currentValue == optionValue;
                options += `<option value="${optionValue}" ${isSelected ? 'selected' : ''}>${optionLabel}</option>`;
            });
            
            return `
                <select class="form-select" id="${fieldId}" ${config.required ? 'required' : ''}>
                    ${config.placeholder ? `<option value="">${config.placeholder}</option>` : ''}
                    ${options}
                </select>
            `;
            
        default:
            return `
                <input type="text" class="form-control" id="${fieldId}"
                       value="${currentValue}" 
                       ${config.required ? 'required' : ''}
                       placeholder="${config.placeholder || ''}">
            `;
    }
}

// Рендер простых параметров
function renderSimpleParameters(parametersStructure, currentParameters) {
    let html = '';
    
    Object.keys(parametersStructure).forEach(paramKey => {
        const config = parametersStructure[paramKey];
        const currentValue = currentParameters[paramKey] !== undefined ? currentParameters[paramKey] : (config.default || '');
        
        html += `
            <div class="parameter-item">
                <label class="form-label small fw-bold">${config.label}</label>
                ${config.description ? `<div class="form-text text-muted mb-2">${config.description}</div>` : ''}
                ${renderSimpleParameterField(paramKey, config, currentValue)}
            </div>
        `;
    });
    
    return html;
}

// Сохранение простых настроек
function saveSimpleSettings() {
    if (!currentFilterId) {
        showNotification('Ошибка: не выбран фильтр', 'error');
        return;
    }

    // Создаем FormData с нуля
    const fd = new FormData();
    fd.append('_token', '{{ csrf_token() }}');
    fd.append('_method', 'PUT');
    fd.append('command', 'save-settings');
    
    // Добавляем основные настройки
    fd.append('name', $('#filterName').val());
    fd.append('type', $('#filterType').val());
    fd.append('handler', $('#filterHandler').val());
    fd.append('description', $('#filterDescription').val());
    fd.append('order', $('#filterOrder').val());
    fd.append('enabled', $('#filterEnabled').is(':checked') ? '1' : '0');

    // Добавляем параметры (включая debug_mode)
    Object.keys(currentParametersStructure).forEach(paramKey => {
        const fieldId = `param_${paramKey}`;
        const field = document.getElementById(fieldId);
        
        if (field) {
            let value = '';
            
            // Обработка разных типов полей
            if (field.type === 'checkbox') {
                value = field.checked ? '1' : '0';
            } else if (field.type === 'select-one') {
                value = field.options[field.selectedIndex].value;
            } else {
                value = field.value;
            }
            
            fd.append(`parameters[${paramKey}]`, value);
        }
    });

    // Добавляем debug_mode отдельно (он в основных настройках)
    fd.append('parameters[debug_enabled]', $('#filterDebugMode').is(':checked') ? '1' : '0');

    // Показываем индикатор загрузки
    const saveBtn = $('#settingsModal .btn-success');
    saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Сохранение...');

    // Отправляем FormData через jQuery Ajax
    $.ajax({
        url: `/management/assistant/filters/${currentFilterId}`,
        type: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        success: function(response) {
            saveBtn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Сохранить настройки');
            
            if (response.success) {
                $('#settingsModal').modal('hide');
                showNotification(response.message, 'success');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showNotification(response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            saveBtn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Сохранить настройки');
            let errorMessage = 'Ошибка сохранения настроек';
            
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage += ': ' + xhr.responseJSON.message;
            } else {
                errorMessage += ': ' + error;
            }
            
            showNotification(errorMessage, 'error');
        }
    });
}

// Функция уведомлений
function showNotification(message, type = 'success') {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
    
    const notification = $(`
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 1060; min-width: 300px;">
            <i class="fas ${icon} me-2"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    
    $('body').append(notification);
    
    setTimeout(() => {
        notification.alert('close');
    }, 5000);
}

// Drag & Drop для карточек
$(document).ready(function(){
    // Сортировка карточек
    $("#filters-container").sortable({
        handle: ".card",
        update: function(event, ui) {
            $('#filters-container .col-lg-6').each(function(index) {
                const filterId = $(this).data('id');
                updateFilterOrder(filterId, index);
            });
        }
    }).disableSelection();

    // Обновление порядка фильтра
    function updateFilterOrder(filterId, order) {
        const fd = new FormData();
        fd.append('_token', '{{ csrf_token() }}');
        fd.append('_method', 'PUT');
        fd.append('command', 'change-property');
        fd.append('property_id', '112');
        fd.append('value', order);

        $.ajax({
            url: `/management/assistant/filters/${filterId}`,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function() {
                // Обновляем отображение порядка
                $(`[data-id="${filterId}"] .filter-type-badge:contains("Порядок:")`)
                    .text(`Порядок: ${order}`);
            }
        });
    }

    // Переключение статуса
    $('.status-switch').on('change', function() {
        const filterId = $(this).data('filter-id');
        const isActive = $(this).is(':checked');
        
        const fd = new FormData();
        fd.append('_token', '{{ csrf_token() }}');
        fd.append('_method', 'PUT');
        fd.append('command', 'change-property');
        fd.append('property_id', '116');
        fd.append('value', isActive ? '1' : '0');

        $.ajax({
            url: `/management/assistant/filters/${filterId}`,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function() {
                const card = $(`.status-switch[data-filter-id="${filterId}"]`).closest('.filter-card');
                if (isActive) {
                    card.removeClass('border-secondary').addClass('border-primary');
                } else {
                    card.removeClass('border-primary').addClass('border-secondary');
                }
                
                showNotification(`Фильтр ${isActive ? 'активирован' : 'деактивирован'}`, 'success');
            }
        });
    });
});
</script>
@endpush

@endsection
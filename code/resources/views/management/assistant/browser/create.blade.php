@extends('template.template',[
	'title'=>'Новый сценарий браузера',
])
@push('sidebar') @include('management.sidebar') @endpush
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-code"></i> Создание сценария браузера
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('m.assistant.browser.index') }}" class="btn btn-sm btn-default">
                            <i class="fas fa-arrow-left"></i> Назад
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form id="scenarioForm" action="{{ route('m.assistant.browser.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>Мета-информация</label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <input type="text" name="meta[source]" class="form-control" 
                                                   placeholder="Источник (например: web_interface)" value="web_interface">
                                        </div>
                                        <div class="col-md-6">
                                            <input type="text" name="meta[description]" class="form-control" 
                                                   placeholder="Описание сценария">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Команды сценария</label>
                                    <div id="commandsContainer">
                                        <!-- Команды будут добавляться здесь -->
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addCommand()">
                                        <i class="fas fa-plus"></i> Добавить команду
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Готовые пресеты</h5>
                                    </div>
                                    <div class="card-body">
                                        @foreach($presets as $key => $preset)
                                        <button type="button" class="btn btn-outline-secondary btn-block mb-2" 
                                                onclick="loadPreset('{{ $key }}')">
                                            {{ $preset['name'] }}
                                        </button>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h5 class="card-title">Действия</h5>
                                    </div>
                                    <div class="card-body">
                                        <button type="submit" class="btn btn-success btn-block">
                                            <i class="fas fa-play"></i> Выполнить сценарий
                                        </button>
                                        <button type="button" class="btn btn-info btn-block mt-2" onclick="previewJson()">
                                            <i class="fas fa-code"></i> Предпросмотр JSON
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JSON Preview Modal -->
<div class="modal fade" id="jsonPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Предпросмотр JSON</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <pre id="jsonPreview"><code></code></pre>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.command-block {
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 15px;
    background: #f9f9f9;
}
.command-header {
    cursor: move;
    padding: 5px 0;
    margin-bottom: 10px;
    border-bottom: 1px solid #eee;
}
</style>
@endpush

@push('scripts')
<script>
// Шаблоны команд
const commandTemplates = {
    browse: {
        action: 'browse',
        url: 'https://example.com',
        options: { screenshot: true, text: false }
    },
    search: {
        action: 'search', 
        query: 'поисковый запрос',
        engine: 'google',
        options: { screenshot: true, text: true }
    },
    extract_text: {
        action: 'extract_text',
        selector: 'h1, h2, h3'
    },
    click: {
        action: 'click', 
        selector: 'button.submit'
    },
    fill: {
        action: 'fill',
        selector: 'input[name="q"]',
        value: 'текст для заполнения'
    },
    screenshot: {
        action: 'screenshot',
        full_page: false
    }
};

// Готовые пресеты
const presets = @json($presets);

// Добавить команду
function addCommand(type = 'browse') {
    const container = document.getElementById('commandsContainer');
    const index = container.children.length;
    
    const template = `
        <div class="command-block" data-index="${index}">
            <div class="command-header d-flex justify-content-between align-items-center">
                <strong>Команда #${index + 1}</strong>
                <div>
                    <select class="form-control form-control-sm d-inline-block w-auto" 
                            onchange="changeCommandType(${index}, this.value)">
                        <option value="browse">Перейти</option>
                        <option value="search">Поиск</option>
                        <option value="extract_text">Извлечь текст</option>
                        <option value="click">Клик</option>
                        <option value="fill">Заполнить поле</option>
                        <option value="screenshot">Скриншот</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-danger ml-2" onclick="removeCommand(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div id="commandFields_${index}">
                <!-- Поля команды будут рендериться здесь -->
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', template);
    renderCommandFields(index, type);
}

// Удалить команду
function removeCommand(index) {
    const element = document.querySelector(`[data-index="${index}"]`);
    if (element) {
        element.remove();
        reindexCommands();
    }
}

// Переиндексация команд после удаления
function reindexCommands() {
    const blocks = document.querySelectorAll('.command-block');
    blocks.forEach((block, newIndex) => {
        block.setAttribute('data-index', newIndex);
        block.querySelector('.command-header strong').textContent = `Команда #${newIndex + 1}`;
    });
}

// Сменить тип команды
function changeCommandType(index, type) {
    renderCommandFields(index, type);
}

// Рендер полей команды
function renderCommandFields(index, type) {
    const container = document.getElementById(`commandFields_${index}`);
    const template = commandTemplates[type] || commandTemplates.browse;
    
    let fieldsHtml = '';
    
    switch(type) {
        case 'browse':
            fieldsHtml = `
                <div class="form-group">
                    <label>URL адрес</label>
                    <input type="url" name="commands[${index}][url]" 
                           class="form-control" value="${template.url}" required>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="commands[${index}][options][screenshot]" 
                           class="form-check-input" ${template.options.screenshot ? 'checked' : ''}>
                    <label class="form-check-label">Сделать скриншот</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="commands[${index}][options][text]" 
                           class="form-check-input" ${template.options.text ? 'checked' : ''}>
                    <label class="form-check-label">Сохранить текст страницы</label>
                </div>
            `;
            break;
            
        case 'search':
            fieldsHtml = `
                <div class="form-group">
                    <label>Поисковый запрос</label>
                    <input type="text" name="commands[${index}][query]" 
                           class="form-control" value="${template.query}" required>
                </div>
                <div class="form-group">
                    <label>Поисковая система</label>
                    <select name="commands[${index}][engine]" class="form-control">
                        <option value="google" ${template.engine === 'google' ? 'selected' : ''}>Google</option>
                        <option value="yandex" ${template.engine === 'yandex' ? 'selected' : ''}>Yandex</option>
                        <option value="bing" ${template.engine === 'bing' ? 'selected' : ''}>Bing</option>
                    </select>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="commands[${index}][options][screenshot]" 
                           class="form-check-input" ${template.options.screenshot ? 'checked' : ''}>
                    <label class="form-check-label">Сделать скриншот</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="commands[${index}][options][text]" 
                           class="form-check-input" ${template.options.text ? 'checked' : ''}>
                    <label class="form-check-label">Сохранить текст страницы</label>
                </div>
            `;
            break;
            
        case 'extract_text':
            fieldsHtml = `
                <div class="form-group">
                    <label>CSS селектор</label>
                    <input type="text" name="commands[${index}][selector]" 
                           class="form-control" value="${template.selector}" required>
                    <small class="form-text text-muted">Например: h1, .title, #content</small>
                </div>
            `;
            break;
            
        case 'click':
            fieldsHtml = `
                <div class="form-group">
                    <label>CSS селектор элемента</label>
                    <input type="text" name="commands[${index}][selector]" 
                           class="form-control" value="${template.selector}" required>
                </div>
            `;
            break;
            
        case 'fill':
            fieldsHtml = `
                <div class="form-group">
                    <label>CSS селектор поля</label>
                    <input type="text" name="commands[${index}][selector]" 
                           class="form-control" value="${template.selector}" required>
                </div>
                <div class="form-group">
                    <label>Значение для заполнения</label>
                    <input type="text" name="commands[${index}][value]" 
                           class="form-control" value="${template.value}" required>
                </div>
            `;
            break;
            
        case 'screenshot':
            fieldsHtml = `
                <div class="form-check">
                    <input type="checkbox" name="commands[${index}][full_page]" 
                           class="form-check-input" ${template.full_page ? 'checked' : ''}>
                    <label class="form-check-label">Полностраничный скриншот</label>
                </div>
            `;
            break;
    }
    
    // Добавляем скрытое поле с действием
    fieldsHtml += `<input type="hidden" name="commands[${index}][action]" value="${type}">`;
    
    container.innerHTML = fieldsHtml;
}

// Загрузить пресет
function loadPreset(presetKey) {
    if (presets[presetKey]) {
        document.getElementById('commandsContainer').innerHTML = '';
        
        presets[presetKey].commands.forEach((command, index) => {
            addCommand(command.action);
            // Здесь нужно заполнить поля команды значениями из пресета
            setTimeout(() => {
                fillCommandFields(index, command);
            }, 100);
        });
    }
}

// Заполнить поля команды
function fillCommandFields(index, command) {
    const container = document.getElementById(`commandFields_${index}`);
    
    Object.keys(command).forEach(key => {
        const input = container.querySelector(`[name="commands[${index}][${key}]"]`);
        if (input) {
            if (input.type === 'checkbox') {
                input.checked = command[key];
            } else {
                input.value = command[key];
            }
        }
    });
}

// Предпросмотр JSON
function previewJson() {
    const formData = new FormData(document.getElementById('scenarioForm'));
    const commands = [];
    const meta = {};
    
    // Парсим formData в структурированный объект
    for (let [key, value] of formData.entries()) {
        if (key.startsWith('commands[')) {
            const match = key.match(/commands\[(\d+)\]\[(.+)\]/);
            if (match) {
                const index = parseInt(match[1]);
                const field = match[2];
                
                if (!commands[index]) commands[index] = {};
                
                if (field.includes('[')) {
                    // Обработка вложенных полей (options)
                    const nestedMatch = field.match(/(\w+)\[(\w+)\]/);
                    if (nestedMatch) {
                        if (!commands[index][nestedMatch[1]]) commands[index][nestedMatch[1]] = {};
                        commands[index][nestedMatch[1]][nestedMatch[2]] = value === 'on' ? true : value;
                    }
                } else {
                    commands[index][field] = value === 'on' ? true : value;
                }
            }
        } else if (key.startsWith('meta[')) {
            const field = key.match(/meta\[(.+)\]/)[1];
            meta[field] = value;
        }
    }
    
    const requestData = {
        request_id: 'preview_' + Date.now(),
        commands: commands.filter(cmd => cmd), // Убираем пустые
        meta: meta
    };
    
    document.getElementById('jsonPreview').textContent = JSON.stringify(requestData, null, 2);
    $('#jsonPreviewModal').modal('show');
}

// Инициализация - добавляем первую команду по умолчанию
document.addEventListener('DOMContentLoaded', function() {
    addCommand('browse');
});
</script>
@endpush
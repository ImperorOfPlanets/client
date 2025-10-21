@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Редактирование шаблона промта</h1>
    <form action="{{ route('ai.promts.update', $promt) }}" method="POST" id="templateForm">
        @csrf
        @method('PUT')

        <div class="card mb-4">
            <div class="card-body">
                <div class="form-group mb-3">
                    <label for="name">Уникальное название шаблона *</label>
                    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $promt->name) }}" required>
                    <small class="form-text text-muted">Используйте понятное название, например "Приветствие клиента"</small>
                </div>

                <div class="form-group mb-3">
                    <label for="description">Описание шаблона</label>
                    <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $promt->description) }}</textarea>
                    <small class="form-text text-muted">Необязательное описание назначения шаблона</small>
                </div>

                <div class="form-group mb-3">
                    <label for="tags">Теги</label>
                    <input type="text" name="tags" id="tags" class="form-control" value="{{ old('tags', isset($promt) ? implode(', ', $promt->settings['tags'] ?? []) : '') }}" placeholder="Введите теги через запятую">
                    <small class="form-text text-muted">Разделяйте теги запятыми, например: news, digest, welcome</small>
                </div>

                <div class="form-group mb-3">
                    <label for="text">Текст промта *</label>
                    <textarea name="text" id="text" class="form-control" rows="5" required>{{ old('text', $promt->getText()) }}</textarea>
                    <small class="form-text text-muted">Вы можете вставлять переменные типа ***переменная***, такие как имена пользователей или другие данные.</small>
                </div>

                <div class="form-group mb-3">
                    <label>Переменные</label>
                    <ul id="variable-list" class="list-unstyled">
                        <!-- Здесь выводятся переменные -->
                        @foreach($promt->getVariables() as $key => $variable)
                            <li class="mb-3 d-flex align-items-center" data-key="{{ $key }}"
                                data-name="{{ $variable['name'] }}"
                                data-type="{{ $variable['type'] }}"
                                data-description="{{ $variable['description'] }}"
                                data-required="{{ json_encode($variable['required']) }}">
                                <div class="mr-auto p-2 flex-grow-1">
                                    <strong>{{ $variable['name'] }}</strong> ({{ $variable['type'] }})
                                    @if(isset($variable['description']) && trim($variable['description']) !== '')
                                        <br />
                                        {!! '<small>' . e($variable['description']) . '</small>' !!}
                                    @endif
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm edit-variable-btn mr-2" title="Изменить" data-toggle="tooltip" data-placement="top" data-original-title="Изменить переменную">
                                    <i class="fa fa-edit"></i> Редактировать
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm delete-variable-btn" title="Удалить" data-toggle="tooltip" data-placement="top" data-original-title="Удалить переменную">
                                    <i class="fa fa-trash"></i> Удалить
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <button type="button" class="btn btn-success btn-sm add-variable-btn"><i class="fa fa-plus"></i> Добавить переменную</button>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('ai.promts.index') }}" class="btn btn-secondary">
                Отмена
            </a>
            <button type="submit" class="btn btn-primary">
                Сохранить изменения
            </button>
        </div>
    </form>

    <!-- Модальное окно для редактирования/создания переменной -->
    <div class="modal fade" id="editVariableModal" tabindex="-1" role="dialog" aria-labelledby="editVariableModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editVariableModalLabel">Редактирование переменной</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Закрыть">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="variable-name">Название переменной *</label>
                        <input type="hidden" name="variable-id" id="variable-id"/>
                        <input type="text" class="form-control" id="variable-name" required/>
                    </div>
                    <div class="form-group">
                        <label for="variable-type">Тип переменной *</label>
                        <select class="custom-select" id="variable-type" required>
                            <option value="">Выберите...</option>
                            <option value="string">Строка</option>
                            <option value="integer">Число</option>
                            <option value="boolean">Логическое значение</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="variable-description">Описание переменной</label>
                        <textarea class="form-control" id="variable-description" rows="3"></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="variable-required" checked>
                        <label class="form-check-label" for="variable-required">Обязательная переменная?</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отменить</button>
                    <button type="button" class="btn btn-primary save-variable-btn">Сохранить переменную</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    document.getElementById('templateForm').addEventListener('submit', function(e) {
        // Удаляем старые скрытые поля переменных, если они есть
        document.querySelectorAll('input[name^="variables["]').forEach(el => el.remove());

        // Собираем все переменные из списка
        const variables = [];
        document.querySelectorAll('#variable-list li').forEach((li, index) => {
            variables.push({
                name: li.dataset.name,
                type: li.dataset.type,
                description: li.dataset.description,
                required: li.dataset.required === 'true'
            });
        });

        // Создаем скрытые input'ы для каждой переменной
        variables.forEach((variable, index) => {
            const prefix = `variables[${index}]`;
            createHiddenInput(prefix + '[name]', variable.name);
            createHiddenInput(prefix + '[type]', variable.type);
            createHiddenInput(prefix + '[description]', variable.description || '');
            createHiddenInput(prefix + '[required]', variable.required ? '1' : '0');
        });

        function createHiddenInput(name, value) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            document.getElementById('templateForm').appendChild(input);
        }
    });

    let variableIndex = {{ count($promt->getVariables()) }}; // Инициализируем индекс на основе существующих переменных

    // Исправлен селектор кнопки добавления
    $(document).on('click', '.add-variable-btn', function () {
        openEditVariableModal(null);
    });

    $(document).on('click', '.edit-variable-btn', function () {
        const key = $(this).closest('li').data('key');
        openEditVariableModal(key);
    });

    $(document).on('click', '.delete-variable-btn', function () {
        $(this).closest('li').remove();
    });

    $('.save-variable-btn').on('click', function () {
        const key = $('#variable-id').val();
        const name = $('#variable-name').val().trim();
        const type = $('#variable-type').val();
        const description = $('#variable-description').val().trim();
        const isRequired = $('#variable-required').prop('checked');

        if (!name || !type) {
            alert("Заполните обязательные поля: название и тип");
            return;
        }

        const newVariable = {
            name: name,
            type: type,
            description: description,
            required: isRequired
        };

        if (key !== '') {
            updateVariable(key, newVariable);
        } else {
            addVariable(newVariable);
        }

        $('#editVariableModal').modal('hide');
    });

    function openEditVariableModal(key) {
        $('#variable-id').val(key || '');
        
        if (key !== null && key !== '') {
            const $li = $(`li[data-key="${key}"]`);
            $('#variable-name').val($li.data('name'));
            $('#variable-type').val($li.data('type'));
            $('#variable-description').val($li.data('description'));
            $('#variable-required').prop('checked', $li.data('required') === 'true');
        } else {
            $('#variable-name').val('');
            $('#variable-type').val('');
            $('#variable-description').val('');
            $('#variable-required').prop('checked', true);
        }

        $('#editVariableModal').modal('show');
    }

    function addVariable(variable) {
        const key = variableIndex++;
        const $li = $(`
        <li class="mb-3 d-flex align-items-center" 
            data-key="${key}"
            data-name="${variable.name}"
            data-type="${variable.type}"
            data-description="${variable.description}"
            data-required="${variable.required}">
                <div class="mr-auto p-2 flex-grow-1">
                    <strong>${variable.name}</strong> (${variable.type})
                    ${variable.description ? `<br><small>${variable.description}</small>` : ''}
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm edit-variable-btn mr-2">
                    <i class="fa fa-edit"></i> Редактировать
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm delete-variable-btn">
                    <i class="fa fa-trash"></i> Удалить
                </button>
            </li>
        `);
        $('#variable-list').append($li);
    }

    function updateVariable(key, variable) {
        const $li = $(`li[data-key="${key}"]`);
        $li.attr({
            'data-name': variable.name,
            'data-type': variable.type,
            'data-description': variable.description,
            'data-required': variable.required
        }).find('.flex-grow-1').html(`
            <strong>${variable.name}</strong> (${variable.type})
            ${variable.description ? `<br><small>${variable.description}</small>` : ''}
        `);
    }
});
</script>
@endsection
@extends('template.template',[
	'title'=>'Парсеры'
])
@section('content')
@push('scripts')
<script src="/js/jquery-ui/jquery-ui.min.js"></script>
<script>
    $(function () {
        const actionsData = []; // Массив для хранения данных действий

        // Активируем sortable для левого списка
        $("#added-actions").sortable({
            placeholder: "ui-state-highlight",
            start: function (event, ui) {
                const addedActionsWidth = $("#added-actions").width();
                ui.placeholder.css({
                    width: addedActionsWidth,
                    height: ui.helper.outerHeight()
                });
                // Устанавливаем ширину перетаскиваемого элемента (если нужно)
                ui.helper.css({
                    width: addedActionsWidth,
                    "box-sizing": "border-box"
                });
            },
            sort: function (event, ui) {
                // Проверяем, находится ли элемент внутри #added-actions
                const container = $("#added-actions");
                const helperOffset = ui.helper.offset();
                const containerOffset = container.offset();

                const helperTop = helperOffset.top;
                const helperLeft = helperOffset.left;
                const helperBottom = helperTop + ui.helper.outerHeight();
                const helperRight = helperLeft + ui.helper.outerWidth();

                const containerTop = containerOffset.top;
                const containerLeft = containerOffset.left;
                const containerBottom = containerTop + container.outerHeight();
                const containerRight = containerLeft + container.outerWidth();

                // Проверяем, находится ли элемент за пределами контейнера
                if (
                    helperTop < containerTop ||
                    helperLeft < containerLeft ||
                    helperBottom > containerBottom ||
                    helperRight > containerRight
                ) {
                    // Подсвечиваем элемент красным
                    ui.helper.addClass("remove-highlight").removeClass("add-highlight");
                } else {
                    // Убираем красную подсветку
                    ui.helper.removeClass("remove-highlight").addClass("add-highlight");
                }
            },
            stop: function (event, ui) {
                // Проверяем, находится ли элемент за пределами #added-actions
                const container = $("#added-actions");
                const helperOffset = ui.offset; // Позиция элемента
                const containerOffset = container.offset();

                const helperTop = helperOffset.top;
                const helperLeft = helperOffset.left;
                const helperBottom = helperTop + ui.item.outerHeight();
                const helperRight = helperLeft + ui.item.outerWidth();

                const containerTop = containerOffset.top;
                const containerLeft = containerOffset.left;
                const containerBottom = containerTop + container.outerHeight();
                const containerRight = containerLeft + container.outerWidth();

                if (
                    helperTop < containerTop ||
                    helperLeft < containerLeft ||
                    helperBottom > containerBottom ||
                    helperRight > containerRight
                ) {
                    // Если элемент за пределами, удаляем его
                    ui.item.remove();
                } else {
                    // Убираем красную и зеленую подсветку, если он в пределах
                    ui.item.removeClass("remove-highlight add-highlight");
                }
            },
            receive: function (event, ui) {
                const originalItem = ui.helper.clone(true, true); // Копия элемента из all-actions
                const newId = `added-action-${Date.now()}`;

                // Обновляем ID клонированного элемента
                originalItem.attr("id", newId).removeClass("remove-highlight").addClass("add-highlight");

                // Убираем стиль от jQuery UI и добавляем элемент
                ui.helper.remove(); // Убираем временный helper
                $(this).append(originalItem); // Добавляем копию в список
            },
            remove: function (event, ui) {
                // Подсветка элемента перед удалением
                ui.item.addClass("removing-item");
                setTimeout(() => {
                    ui.item.remove(); // Удаление элемента через 500 мс
                }, 500);
            }
        }).disableSelection();

        // Активируем возможность перетаскивания из правой колонки в левую
        $("#all-actions li").draggable({
            connectToSortable: "#added-actions",
            helper: "clone",
            revert: "invalid",
            start: function (event, ui) {
                // Устанавливаем ширину helper под ширину #added-actions
                const addedActionsWidth = $("#added-actions").width();
                ui.helper.css({
                    width: addedActionsWidth,
                    "box-sizing": "border-box" // Учет отступов и границ
                });
            }
        });

        // Настройка droppable для добавления зеленой подсветки
        $("#added-actions").droppable({
            over: function (event, ui) {
                // Подсвечиваем helper зеленым, если он над областью #added-actions
                ui.helper.addClass("add-highlight").removeClass("remove-highlight ui-sortable-hover");
            },
            out: function (event, ui) {
                // Убираем зеленую подсветку, если он покидает область #added-actions
                ui.helper.removeClass("add-highlight").addClass("remove-highlight");
            }
        });
    });
</script>
@endpush
@push('styles')
    <link rel="stylesheet" href="/js/jquery-ui/jquery-ui.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .ui-state-highlight {
            background: #f0f0f0;
            border: 2px dashed #007bff;
            height: 2.5em;
            line-height: 2.5em;
            margin-bottom: 0.5em;
            text-align: center;
        }

        .ui-sortable-hover {
            /* Убедитесь, что этот стиль не перекрывает зеленую подсветку */
            border: 2px dashed #28a745;
            background-color: transparent; /* Уберите фон */
        }

        .ui-sortable-highlight {
            border: 2px solid #ffc107;
        }

        /* Контейнер для добавленных заданий */
        #added-actions {
            min-height: 50px;
            border: 2px dashed #ccc;
            background-color: #f8f9fa;
            padding: 10px;
            width: 100%;
            box-sizing: border-box;
        }

        #added-actions .list-group-item {
            margin-bottom: 10px;
            width: 100% !important;
        }

        .remove-highlight {
            background-color: #f8d7da !important;
            border: 2px solid #f5c2c7 !important;
            color: #842029 !important;
            opacity: 0.9;
        }

        .add-highlight {
            background-color: #d4edda !important;
            border: 2px solid #c3e6cb !important;
            color: #155724 !important;
        }
    </style>
@endpush
@push('sidebar') @include('management.sidebar') @endpush
<form action="{{route('m.settings.parsers.update',['parser'=>$parser->id])}}" method='post'>
	@csrf
	@method('put')
	<div class='p-2'>
		Название
		<input type='text' name='name' class='form-control' value="{{$parser->propertyByID(1)->pivot->value ?? 'Без названия'}}">
	</div>
</form>

<!-- Кнопка для вывода данных -->
<div class="mt-4">
    <button id="save-actions" class="btn btn-success w-100">Сохранить все действия</button>
</div>

<div class="row">
    <!-- Левая колонка -->
    <div class="col-md-6">
        <label for="added-actions" class="form-label">Добавленные действия</label>
        <div class="p-2 border rounded">
            <ul class="list-group connectedSortable" id="added-actions">
                <!-- Здесь будут появляться добавленные действия -->
            </ul>
        </div>
    </div>

    <!-- Правая колонка -->
    <div class="col-md-6">
        <label for="all-actions" class="form-label">Все действия</label>
        <div class="p-2 border rounded">
            <ul class="list-group" id="all-actions">
                @foreach($parsActions as $action)
                    <li class="list-group-item" data-id="{{ $action->id }}">
                        {{ $action->propertyByID(1)->pivot->value }}
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>

<!-- Блок скрытых форм -->
<div id="forms-container" class="mt-4">
    @foreach ($parsActions as $action)
        @php
            $formPath = resource_path("views/management/settings/parsers/forms/{$action->id}.blade.php");
        @endphp
        @if (file_exists($formPath))
            <div id="form-{{ $action->id }}" class="action-form d-none">
                @include("management.settings.parsers.forms.{$action->id}")
            </div>
        @endif
    @endforeach
</div>

<!-- Общее модальное окно -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="dynamicActionForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalLabel">Форма для действия</h5>
                    <button type="button" id="closeModal" class="btn-close" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Здесь будет динамически вставляться форма -->
                    <p>Загрузка формы...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" id="cancelModal" class="btn btn-secondary">Закрыть</button>
                    <button type="button" id="saveActionData" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    // Обработчик клика по элементу в левой колонке
    $("#added-actions").on("click", ".list-group-item", function () {
        const actionId = $(this).data("id"); // Получаем ID действия
        const actionName = $(this).text(); // Название действия

        // Устанавливаем заголовок модального окна
        $("#actionModalLabel").text(`Настройки для действия: ${actionName}`);

        // Копируем содержимое нужной формы в модальное окно
        const formContent = $(`#form-${actionId}`).html();
        $("#modalBody").html(formContent);

        // Показываем модальное окно (через добавление класса)
        $("#actionModal").css("display", "block").addClass("show");
    });

    // Закрытие модального окна
    const closeModal = () => {
        $("#actionModal").css("display", "none").removeClass("show");
        $("#modalBody").html(""); // Очищаем содержимое модального окна
    };

    // Привязываем действия к кнопкам закрытия
    $("#closeModal, #cancelModal").on("click", closeModal);

    // Сохранение данных формы из модального окна
    $("#saveActionData").on("click", function () {
        const formData = {};
        const inputs = $("#modalBody").find("input, select, textarea");

        // Сохраняем данные из формы
        inputs.each(function () {
            const name = $(this).attr("name");
            const value = $(this).val();
            formData[name] = value;
        });

        console.log("Сохраненные данные формы:", formData);
        closeModal(); // Закрытие модального окна после сохранения
    });
</script>
@endsection
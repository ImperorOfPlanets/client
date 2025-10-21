@extends('template.template',[
	'title'=>'PWA'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush
@push('scripts')
    <script src="/js/croppr.min.js"></script>
@endpush
@push('styles')
    <link href="/css/croppr.min.css" rel="stylesheet">
    <style>
        #cropModal {
            display: none; /* Скрываем модальное окно по умолчанию */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
        }
    
        .modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
        }
    
        .modal-content img {
            max-width: 100%;
            height: auto;
        }
    
        button {
            margin: 10px 5px;
            padding: 10px 15px;
            border: none;
            cursor: pointer;
        }
    
        .btn-success {
            background-color: #28a745;
            color: white;
        }
    
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
    
        button:hover {
            opacity: 0.9;
        }
    </style>
@endpush

<section class="p-2">
    <div class="row">
        <!-- Левая колонка: настройки -->
        <div class="col-md-6">
            <h2>PWA Manifest (Настройки)</h2>
            <form id="manifestForm" class="p-2">
                <div class="form-group">
                    <label for="name">App Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="{{ $imagesSettingsJson['name'] ?? 'Your App Name' }}">
                </div>

                <div class="form-group">
                    <label for="short_name">Short Name</label>
                    <input type="text" class="form-control" id="short_name" name="short_name" value="{{ $imagesSettingsJson['short_name'] ?? 'App' }}">
                </div>

                <div class="form-group">
                    <label for="start_url">Start URL</label>
                    <input type="text" class="form-control" id="start_url" name="start_url" value="{{ $imagesSettingsJson['start_url'] ?? '/' }}">
                </div>

                <div class="form-group">
                    <label for="display">Display</label>
                    <input type="text" class="form-control" id="display" name="display" value="{{ $imagesSettingsJson['display'] ?? 'standalone' }}">
                </div>

                <div class="form-group">
                    <label for="background_color">Background Color</label>
                    <input type="color" class="form-control" id="background_color" name="background_color" value="{{ $imagesSettingsJson['background_color'] ?? '#ffffff' }}">
                </div>

                <div class="form-group">
                    <label for="theme_color">Theme Color</label>
                    <input type="color" class="form-control" id="theme_color" name="theme_color" value="{{ $imagesSettingsJson['theme_color'] ?? '#000000' }}">
                </div>
            </form>
        </div>

        <!-- Правая колонка: превью -->
        <div class="col-md-6">
            <h2>Загрузить изображение для всех размеров</h2>
            <div class="preview-section">
                <div class="preview-section">
                    <img 
                        src="{{ file_exists(public_path('img/pwa/forAll.png')) ? asset('img/pwa/forAll.png') : '' }}" 
                        alt="Image Preview" 
                        id="image-preview" 
                        class="img-fluid" 
                    />
                </div>
            </div>
            <div class="mt-4">
                <input type="file" id="uploadImage" accept="image/*" class="form-control d-none">
                <button id="uploadButton" class="btn btn-primary">
                    {{ file_exists(public_path('img/pwa/forAll.png')) ? 'Изменить изображение' : 'Загрузить изображение' }}
                </button>
                <button id="resizeALLButton" class="btn btn-primary">
                    Создать все изображения с соотношением 1:1 из готового
                </button>
            </div>
        </div>
    </div>
</section>

<button id="generate" class="btn btn-success w-100">Сгенерировать manifest</button>

<script>
//Манифест
document.querySelectorAll('#manifestForm input').forEach(input => {
    input.addEventListener('change', function () {
        const formData = new FormData();
        formData.append('value', this.value);
        formData.append('key', this.getAttribute('name'));
        formData.append('command', 'change-value');

        // Отправка данных на сервер с помощью jQuery
        $.ajax({
            url: '/management/settings/pwa',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    alert('Настройка обновлена успешно!');
                } else {
                    alert('Ошибка при обновлении настройки.');
                }
            },
            error: function (xhr, status, error) {
                console.error('Ошибка:', error);
                alert('Ошибка при отправке данных.');
            }
        });
    });
});
//Для всех изображений
$(document).ready(function () {
    $('#generate').click(function(e){
        // Формируем данные для отправки
        const formData = new FormData();
        formData.append('command', 'generate');

        // Отправляем файл на сервер
        $.ajax({
            url: '/management/settings/pwa',
            method: 'POST',
            data: formData,
            success: function (response) {
                alert('Манифест успешно сгенерирлован.');
            },
            error: function (error) {
                console.error(error);
                alert('Ошибка при генерации.');
            }
        });
    });
    // Обработка клика по кнопке
    $('#uploadButton').click(function () {
        const fileInput = $('#uploadImage');
        
        // Если файл еще не выбран, запускаем выбор файла
        if (!fileInput.val()) {
            fileInput.click();
            return;
        }
    });

    // Устанавливаем обработчик для выбора файла
    $('#uploadImage').on('change', function () {
        const fileInput = $('#uploadImage');
        // Получаем файл
        const file = fileInput[0].files[0];
        if (!file) {
            alert('Пожалуйста, выберите изображение.');
            return;
        }

        // Формируем данные для отправки
        const formData = new FormData();
        formData.append('file', file);
        formData.append('command', 'uploadForAll');

        // Отправляем файл на сервер
        $.ajax({
            url: '/management/settings/pwa',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                alert('Изображение успешно загружено и обработано.');
                $('#image-preview').attr('src', URL.createObjectURL(file)).removeClass('d-none');
            },
            error: function (error) {
                console.error(error);
                alert('Ошибка при загрузке изображения.');
            }
        });

    });

    //1:1
    $('#resizeALLButton').click(function(e) {
       // e.preventDefault(); // Предотвращаем стандартное поведение клика
        
        var formData = new FormData();
        formData.append('command', 'resizeAll');

        $.ajax({
            url: '/management/settings/pwa',
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Все изображения были успешно подготовлены!');
                } else {
                    alert('Произошла ошибка при подготовке изображений.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка:', error);
                alert('Произошла ошибка при отправке данных.');
            }
        });
    });
});
</script>

<!-- Модальное окно джля обрезки-->
<div id="cropModal">
    <div class="modal-content">
        <h3>Редактирование изображения</h3>
        <img id="cropImage" src="" alt="Image to crop">
        <br>
        <button id="ready" class="btn btn-success">Готово</button>
        <button onclick="closeCropModal()" class="btn btn-secondary">Отмена</button>
    </div>
</div>

<hr />

<!-- Отдельно каждый элемент -->
@foreach($sizes as $category)
<section class="p-4">
    <h2>{{ $loop->iteration }}. {{ $category['description'] }}</h2>
    <div class="row">
        @foreach($category['icons'] as $icon)
            <div class="col-md-4 mb-4">
                <div class="icon-preview text-center">
                        @if(file_exists(public_path("/img/pwa/icons/".$icon['id'].".png")))
                        <img 
                            src="/img/pwa/icons/{{$icon['id']}}.png"
                            alt="{{ $icon['width'] }}x{{ $icon['height'] }} Icon" 
                            id="{{ $icon['id'] }}-preview"
                            class="img-fluid mb-3" 
                            style="max-width: 100%; height: auto;"
                        />
                        @endif
                        <!-- Если обрезанное изображение не существует, но оригинал есть -->
                        <button class="btn btn-warning" id="{{ $icon['id'] }}-upload-btn">Загрузить изображение</button>
                        <!-- Если ни обрезанного, ни оригинала нет -->
                        <input 
                            type="file" 
                            id="{{ $icon['id'] }}" 
                            class="form-control upload-btn" 
                            onchange="uploadFile('{{ $icon['id'] }}', this)"
                        >
                    <label for="{{ $icon['id'] }}" class="form-label">
                        {{ $icon['width'] }}x{{ $icon['height'] }}
                    </label>
                    <p class="description text-muted">{{ $icon['description'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endforeach

<script>
    function uploadFile(iconId, input) {
        const file = input.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(`${iconId}-preview`).src = e.target.result;
            };
            reader.readAsDataURL(file);

            // Laravel backend integration example:
            const formData = new FormData();
            formData.append('file', file);
            formData.append('iconId', iconId);
            formData.append('command', 'upload');

            fetch(`/management/settings/pwa`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => alert(`Icon ${iconId} uploaded successfully!`))
            .catch(error => console.error('Error uploading file:', error));
        }
    }

    let croppr = null;
    let currentIconId = '';

    function openCropModal(iconId) {
        currentIconId = iconId;

        // Подгрузка оригинального изображения
        const originalSrc = `/storage/originals/${iconId}.png`;
        $('#cropImage').attr('src', originalSrc);

        // Показ модального окна
        $('#cropModal').fadeIn();

        // Инициализация Croppr
        if (croppr) croppr.destroy();
        croppr = new Croppr('#cropImage', {
            aspectRatio: 1,
            onCropEnd: function(data) {
                console.log(data); // Логируем координаты для отладки
            }
        });
    }

    function closeCropModal() {
        $('#cropModal').fadeOut();
    }

    $('#ready').click(function() {
        const data = croppr.getValue();
        const formData = new FormData();
        formData.append('x', data.x);
        formData.append('y', data.y);
        formData.append('width', data.width);
        formData.append('height', data.height);
        formData.append('command', 'crop');
        formData.append('_method', 'PUT');
        formData.append('currentIconId', currentIconId);

        // Отправка запроса на сервер
        $.ajax({
            url: `/management/settings/pwa`,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                alert('Изображение успешно обновлено!');
                location.reload();
            },
            error: function(err) {
                console.error(err);
                alert('Ошибка при обновлении изображения.');
            }
        });

        closeCropModal();
    });

    function replaceFile(iconId) {
        // Замена файла с предварительным обновлением изображения
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = function(event) {
            const file = event.target.files[0];
            const formData = new FormData();
            formData.append('file', file);
            formData.append('iconId', iconId);
            formData.append('command', 'upload');

            // Отправка нового файла на сервер
            $.ajax({
                url: `/management/settings/pwa`,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    alert('Файл успешно обновлен!');
                    location.reload();
                },
                error: function(err) {
                    console.error(err);
                    alert('Ошибка при обновлении файла.');
                }
            });
        };
        input.click();
    }
</script>
@endsection
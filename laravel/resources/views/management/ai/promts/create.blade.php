@extends('layouts.app')
@section('content')
<div class="container">
    <h1>Новый шаблон промта</h1>

    <div class="alert alert-info mb-4">
        Перед созданием шаблона ознакомьтесь с <a href="#template-instruction" class="alert-link">инструкцией по работе с шаблонами</a> внизу страницы.
    </div>

    <form action="{{ route('ai.promts.store') }}" method="POST">
        @csrf

        <div class="card mb-4">
            <div class="card-body">
                <div class="form-group mb-3">
                    <label for="name">Уникальное название шаблона *</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                    <small class="form-text text-muted">Используйте понятное название, например "Приветственный скрипт AI"</small>
                </div>

                <div class="form-group mb-3">
                    <label for="description">Описание шаблона</label>
                    <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                    <small class="form-text text-muted">Необязательное описание назначения шаблона</small>
                </div>

                <div class="form-group mb-3">
                    <label for="tags">Теги</label>
                    <input type="text" name="tags" id="tags" class="form-control" value="{{ old('tags', isset($promt) ? implode(', ', $promt->settings['tags'] ?? []) : '') }}" placeholder="Введите теги через запятую">
                    <small class="form-text text-muted">Разделяйте теги запятыми, например: news, digest, welcome</small>
                </div>

                <div class="form-group mb-3">
                    <label for="text">Текст промта *</label>
                    <textarea name="text" id="text" class="form-control" rows="5" required></textarea>
                    <small class="form-text text-muted">Вы можете вставлять переменные типа ***переменная***, такие как имена пользователей или данные запросов.</small>
                </div>
            </div>
        </div>
        
        <div class="d-flex justify-content-between mb-5">
            <a href="{{ route('ai.promts.index') }}" class="btn btn-secondary">
                Отмена
            </a>
            <button type="submit" class="btn btn-primary">
                Создать шаблон
            </button>
        </div>
    </form>

    <!-- Переход к созданию переменных -->
    <div class="card mt-5">
        <div class="card-body">
            После создания шаблона перейдите в режим редактирования для добавления необходимых переменных.<br/>
            Вы сможете легко управлять всеми переменными шаблона в режиме редактирования.
        </div>
    </div>

    <!-- Инструкция по работе с шаблонами -->
    <div class="card" id="template-instruction">
        <div class="card-header">
            <h2><i class="fas fa-info-circle"></i> Инструкция по работе с шаблонами промтов</h2>
        </div>
        <div class="card-body">
            <h4>Создание нового шаблона</h4>
            <p>При создании шаблона вам потребуется заполнить следующие поля:</p>
            <ul>
                <li><b>Название шаблона:</b> Уникальное наименование вашего шаблона.</li>
                <li><b>Описание:</b> Дополнительная информация о назначении шаблона.</li>
                <li><b>Текст промта:</b> Основной контент промта с поддержкой переменных вида ***переменная***.</li>
            </ul>

            <h4 class="mt-4">Работа с переменными</h4>
            <ol>
                <li>Введённые вами переменные автоматически подставляются в текст промта при запуске.</li>
                <li>Можно определить, является ли переменная обязательной для заполнения при запуске промта.</li>
                <li>Каждая переменная имеет уникальное имя, тип данных и описание её предназначения.</li>
            </ol>

            <h4 class="mt-4">Использование переменных в тексте</h4>
            <p>Внутри текста промта переменные используются следующим образом:</p>
            <pre><code>***переменная***</code></pre>
            <p>Пример использования переменных:</p>
            <pre><code>Здравствуйте, ***user_name***! Ваш запрос № ***request_id*** успешно выполнен.</code></pre>
        </div>
    </div>
</div>
@endsection
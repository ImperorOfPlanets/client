@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush
<div class="container mt-5">
    <h1>Отправка сообщения в публичный канал</h1>
    
    {{-- Форма для отправки текста --}}
    <form id="publicMessageForm" method="POST" action="/management/settings/sockets">
        @csrf
        <input type="hidden" name="command" value="public" />
        <div class="form-group">
            <label for="message">Сообщение:</label>
            <input 
                type="text" 
                class="form-control @error('message') is-invalid @enderror" 
                id="message" 
                name="message" 
                value="{{ old('message') }}" 
                placeholder="Введите сообщение" 
                required>
            
            @error('message')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>
        <div class="mb-3">
            <label for="type" class="form-label">Тип уведомления</label>
            <select id="type" name="type" class="form-select" required>
                <option value="success">Успех</option>
                <option value="warning">Предупреждение</option>
                <option value="danger">Ошибка</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Отправить</button>
    </form>

    {{-- Вывод флеш-сообщений --}}
    @if(session('success'))
        <div class="alert alert-success mt-3">
            {{ session('success') }}
        </div>
    @endif
</div>
<hr />
{{-- Список активных сессий --}}
<div class="container mt-5">
    <h2>Активные сессии</h2>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID Сессии</th>
                <th>ID Пользователя</th>
                <th>IP-адрес</th>
                <th>Информация о браузере</th>
                <th>Последняя активность</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            @foreach($activeSessions as $session)
                <tr>
                    <td>{{ $session->id }}</td>
                    <td>{{ $session->user_id ?? 'Гость' }}</td>
                    <td>{{ $session->ip_address }}</td>
                    <td>{{ $session->user_agent }}</td>
                    <td>{{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->format('Y-m-d H:i:s') }}</td>
                    <td>
                        <button type="button" 
                        class="btn btn-primary open-modal-btn" 
                        data-bs-toggle="modal" 
                        data-bs-target="#sessionModal" 
                        data-session-id="{{ $session->id }}">
                            Открыть
                        </button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>    
</div>

<div class="modal fade" id="sessionModal" tabindex="-1" aria-labelledby="sessionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sessionModalLabel">События для сессии</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <form id="sessionMessageForm" method="POST" action="/management/settings/sockets">
                    @csrf
                    <input type="hidden" name="command" value="for">
                    <input type="hidden" id="modal-session-id" name="session_id" value="">

                    <div class="mb-3">
                        <label for="eventType" class="form-label">Выберите событие</label>
                        <input type="text" id="eventType" name="event" class="form-control" placeholder="Введите название события">
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label">Сообщение</label>
                        <textarea id="message" name="message" class="form-control" rows="3" placeholder="Введите сообщение (необязательно)"></textarea>
                    </div>

                    <button type="submit" class="btn btn-success">Отправить</button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>
<script>
$(document).ready(function () {

    // Обработчик отправки формы для публичного канала через AJAX
    $('#publicMessageForm').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);

        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: formData,
            success: function(response) {
                alert('Сообщение отправлено!');
                $('#publicMessageForm')[0].reset();
            },
            error: function(xhr, status, error) {
                // Показываем ошибку
                var errorMessage = xhr.responseJSON ? xhr.responseJSON.message : "Произошла ошибка";
                $('#publicMessageResult').html('<div class="alert alert-danger mt-3">' + errorMessage + '</div>');
            }
        });
    });

    // Обработчик отправки формы для сессии через AJAX
    $('#sessionMessageForm').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);

        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: formData,
            success: function(response) {
                alert('Сообщение отправлено!');
                $('#sessionMessageForm')[0].reset();
            },
            error: function(xhr, status, error) {
                // Показываем ошибку
                var errorMessage = xhr.responseJSON ? xhr.responseJSON.message : "Произошла ошибка";
                alert(errorMessage); // Или показываем сообщение об ошибке на странице
            }
        });
    });

    // Слушатель кликов по кнопкам открытия модального окна
    document.querySelectorAll('.open-modal-btn').forEach(button => {
        button.addEventListener('click', function () {
            const sessionId = this.getAttribute('data-session-id');
            document.getElementById('modal-session-id').value = sessionId;
        });
    });
});
</script>
@endsection
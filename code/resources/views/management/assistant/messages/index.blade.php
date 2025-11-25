@extends('template.template', [
    'title' => 'Сообщения ассистента'
])

@section('content')
@push('sidebar')
    @include('management.sidebar')
@endpush

<!-- Фильтр сообщений -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Фильтр сообщений</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="/management/assistant/messages" class="row g-3">
            <div class="col-md-4">
                <label for="soc" class="form-label">Социальная сеть</label>
                <select name="soc" id="soc" class="form-select">
                    <option value="">Все социальные сети</option>
                    @foreach($socials as $id => $name)
                        <option value="{{ $id }}" {{ request('soc') == $id ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="chat_id" class="form-label">Chat ID</label>
                <input type="text" name="chat_id" id="chat_id" class="form-control" 
                       value="{{ request('chat_id') }}" placeholder="Введите chat_id">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="d-grid gap-2 d-md-flex">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Показать
                    </button>
                    <a href="/management/assistant/messages" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Сбросить
                    </a>
                </div>
            </div>
        </form>
        
        @if(request()->has('soc') || request()->has('chat_id'))
            <div class="mt-3">
                <small class="text-muted">
                    Фильтр активен: 
                    @if(request('soc'))
                        Соц. сеть: {{ $socials[request('soc')] ?? request('soc') }}
                    @endif
                    @if(request('soc') && request('chat_id')), @endif
                    @if(request('chat_id'))
                        Chat ID: {{ request('chat_id') }}
                    @endif
                </small>
            </div>
        @endif
    </div>
</div>

<table class="table table-bordered table-hover text-center table-responsive">
    <thead>
        <tr>
            <td>ID</td>
            <td>Текст</td>
            <td>Статус</td>
            <td>Социальная сеть</td>
            <td>Chat ID</td>
            <td>Параметры</td>
        </tr>
    </thead>
    <tbody>
        @foreach($messages as $message)
        <tr style="cursor: pointer;" onclick="showMessageDetails({{ $message->id }})">
            <td>{{ $message->id }}</td>
            <td>{{ Str::limit($message->text, 50) }}</td>
            <td>{{ $message->status }}</td>
            <td>{{ $message->social_name }}</td>
            <td>{{ $message->chat_id ?? '—' }}</td>
            <td>
                <button class="btn btn-primary w-100 mb-2" type="button" onclick="event.stopPropagation(); showProcessingLog({{ $message->id }})">
                    Лог обработки
                </button>
                <form method="post" action="/management/assistant/messages/{{ $message->id }}" onsubmit="return confirm('Удалить сообщение?')">
                    @csrf
                    @method('delete')
                    <button class="btn btn-danger w-100" type="submit" onclick="event.stopPropagation()">Удалить</button>
                </form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

{{ $messages->links('vendor.pagination.bootstrap-4') }}

<!-- Модальное окно для деталей сообщения -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="messageModalLabel">Детали сообщения #<span id="messageId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Основная информация</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>ID:</th>
                                <td id="detailId"></td>
                            </tr>
                            <tr>
                                <th>Текст:</th>
                                <td id="detailText"></td>
                            </tr>
                            <tr>
                                <th>Социальная сеть:</th>
                                <td id="detailSocial"></td>
                            </tr>
                            <tr>
                                <th>Chat ID:</th>
                                <td id="detailChatId"></td>
                            </tr>
                            <tr>
                                <th>Статус:</th>
                                <td id="detailStatus"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Поле info (JSON)</h6>
                        <div class="border rounded p-3 bg-light">
                            <pre id="infoJson" class="mb-0" style="max-height: 400px; overflow-y: auto;"></pre>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<!-- Оффканвас для лога обработки -->
<div class="offcanvas offcanvas-end w-50" tabindex="-1" id="processingOffcanvas" aria-labelledby="processingOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="processingOffcanvasLabel">Лог обработки сообщения</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div id="processingLogContent">
            <!-- Лог обработки будет загружен сюда -->
        </div>
    </div>
</div>

<style>
    .table tbody tr:hover {
        background-color: #f5f5f5;
    }
    
    #processingLogContent .list-group-item {
        background-color: #f8f9fa;
        margin-bottom: 8px;
        border-radius: 5px;
        padding: 10px 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #dee2e6;
    }
    
    #processingLogContent .list-group-item strong {
        font-size: 1.1em;
        color: #0d6efd;
        display: block;
        margin-bottom: 5px;
    }
    
    #processingLogContent .list-group-item .status {
        color: #6c757d;
        font-weight: 500;
    }
    
    #processingLogContent .list-group-item .result {
        color: #495057;
        font-size: 0.9em;
        margin: 5px 0;
    }
    
    #processingLogContent .list-group-item .timestamp {
        color: #adb5bd;
        font-size: 0.85em;
        font-style: italic;
        text-align: right;
    }
    
    pre {
        white-space: pre-wrap;
        word-wrap: break-word;
    }
</style>

<script>
function showMessageDetails(messageId) {
    // Показываем загрузку
    $('#messageModalLabel').text('Загрузка...');
    $('#messageId').text(messageId);
    
    // Загружаем данные через AJAX
    $.ajax({
        url: '/management/assistant/messages/' + messageId,
        type: 'GET',
        success: function(response) {
            $('#messageId').text(response.message.id);
            $('#detailId').text(response.message.id);
            $('#detailText').text(response.message.text || '—');
            $('#detailSocial').text(response.message.social_name);
            $('#detailChatId').text(response.message.chat_id || '—');
            $('#detailStatus').text(response.message.status || '—');
            $('#infoJson').text(response.info_json);
            
            // Показываем модальное окно
            new bootstrap.Modal(document.getElementById('messageModal')).show();
        },
        error: function() {
            alert('Ошибка при загрузке данных сообщения');
        }
    });
}

function showProcessingLog(messageId) {
    // Находим строку таблицы с данным messageId
    const row = $(`tr:has(td:contains("${messageId}"))`).first();
    if (row.length === 0) return;
    
    const td = $(this).find('td').eq(5); // 6-я колонка с параметрами
    const processingLog = JSON.parse(td.attr('data-processing-log') || '[]');
    
    // Формируем HTML для лога обработки
    let html = '';
    if (processingLog.length > 0) {
        processingLog.forEach(function(entry) {
            html += `<div class="list-group-item">
                <strong>${entry.stage}</strong>
                <div class="status">Статус: ${entry.status || '—'}</div>
                ${entry.result ? `<div class="result">${entry.result}</div>` : ''}
                <div class="timestamp">${entry.timestamp || ''}</div>
            </div>`;
        });
    } else {
        html = '<p>Информация о процессе обработки отсутствует.</p>';
    }
    
    $('#processingLogContent').html(html);
    
    // Показываем оффканвас
    new bootstrap.Offcanvas(document.getElementById('processingOffcanvas')).show();
}

// Инициализация при загрузке страницы
$(document).ready(function(){
    // Добавляем data-processing-log атрибут к каждой строке
    $('tbody tr').each(function() {
        const td = $(this).find('td').eq(5);
        const processingLog = @json($messages->map(function($msg) { return $msg->processing_log; }));
        const rowIndex = $(this).index();
        if (processingLog[rowIndex]) {
            td.attr('data-processing-log', JSON.stringify(processingLog[rowIndex]));
        }
    });
});
</script>
@endsection
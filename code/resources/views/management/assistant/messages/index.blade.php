@extends('template.template', [
    'title' => 'Welcome'
])

@section('content')
@push('sidebar')
    @include('management.sidebar')
@endpush

<table class="table table-bordered table-hover text-center table-responsive">
    <thead>
        <tr>
            <td>ID</td>
            <td>Текст</td>
            <td>Статус</td>
            <td>Социальная сеть</td>
            <td>Параметры</td>
        </tr>
    </thead>
    <tbody>
        @foreach($messages as $message)
        <tr>
            <td>{{ $message->id }}</td>
            <td text>{{ $message->text }}</td>
            <td>{{ $message->status }}</td>
            <td>{{ $message->social_name ?? '—' }}</td>
            <td 
                data-info="{{ $message->getRawOriginal('info') }}"
                data-processing-log='@json($message->processing_log)'>
                <button class="btn btn-primary w-100 mb-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight" aria-controls="offcanvasRight">Показать</button>
                <form method="post" action="/management/assistant/messages/{{ $message->id }}">
                    @csrf
                    @method('delete')
                    <button class="btn btn-danger w-100" type="submit">Удалить</button>
                </form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

{{ $messages->links('vendor.pagination.bootstrap-4') }}

<div class="offcanvas offcanvas-end w-50" tabindex="-1" id="offcanvasRight" aria-labelledby="offcanvasRightLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasRightLabel">Информация</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <hr>
    <div class="offcanvas-body">
        <div id="messageDetails" class="mb-3">
            <!-- Подробная информация о сообщении, соцсети и отправителе -->
        </div>
        <hr>
        <div id="messageProcessing">
            <h6>Процесс движения сообщений</h6>
            <div id="processingLog" class="list-group">
                <!-- Лог или история обработки будет сюда загружаться -->
            </div>
        </div>
    </div>
</div>

<style>
    #processingLog .list-group-item {
        background-color: #f8f9fa;
        margin-bottom: 8px;
        border-radius: 5px;
        padding: 10px 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    #processingLog .list-group-item strong {
        font-size: 1.1em;
        color: #0d6efd;
        display: block;
        margin-bottom: 5px;
    }
    #processingLog .list-group-item small {
        color: #6c757d;
        font-style: italic;
    }
    #processingLog .list-group-item em {
        color: #adb5bd;
        font-size: 0.85em;
        float: right;
    }
</style>

<script>
$(document).ready(function(){
    const myOffcanvas = document.getElementById('offcanvasRight');
    myOffcanvas.addEventListener('show.bs.offcanvas', function(event) {
        const td = $(event.relatedTarget).closest('td');

        // Парсим данные info и обработку
        const info = JSON.parse(td.attr('data-info'));
        const processingLog = JSON.parse(td.attr('data-processing-log'));

        // Заполняем основную информацию
        $('#messageDetails').html(`
            <p><b>Текст:</b> ${td.siblings('td').eq(1).text()}</p>
            <p><b>Социальная сеть:</b> ${info.soc ?? '—'}</p>
            <p><b>Отправитель:</b> ${info.name ?? '—'}</p>
        `);

        // Формируем красивый список этапов
        if (processingLog.length > 0) {
            let html = '';
            processingLog.forEach(function(entry) {
                html += `<div class="list-group-item">
                    <strong>${entry.stage}</strong>
                    <div>Статус: <span>${entry.status}</span></div>
                    ${entry.result ? `<small>${entry.result}</small>` : ''}
                    <em>${entry.timestamp ?? ''}</em>
                </div>`;
            });
            $('#processingLog').html(html);
        } else {
            $('#processingLog').html('<p>Информация о процессе отсутствует.</p>');
        }
    });
});
</script>
@endsection

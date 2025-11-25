@extends('template.template',[
	'title'=>'queues',
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush
@include('management.settings.queues.panel')

<table class='table table-bordered text-center'>
    <thead>
        <tr>
            <th>
                ID   
            </th>
            <th>
                Данные
            </th>
            <th>
                Ошибка
            </th>
            <th>
                Кнопки
            </th>
        </tr>
    </thead>
    <tbody>
        @foreach($queues as $queue)
            <tr>
                <td>
                    {{$queue->id}}
                </td>
                <td data-payload="{{$queue->payload}}" class='text-center'>
                    <button class="btn btn-success w-75" data-bs-toggle="modal" data-bs-target="#infoModal">Показать</button>
                </td>
                <td data-exception="{{$queue->exception}}" class='text-center'>
                    <button class="btn btn-success w-75" data-bs-toggle="modal" data-bs-target="#infoModal">Показать</button>
                </td>
                <td class='text-center'>
                    <form method='post' action="/management/settings/queues/{{$queue->id}}">
                        @csrf
                        @method('delete')
                        <input type='hidden' name='type' value="failed">
                        <input type='hidden' name='id' value="{{$queue->id}}">
                        <button class="btn btn-danger w-75">Удалить</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<!-- МОДАЛЬНОЕ ОКНО -->
<div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
        <div class="modal-header">
            <h1 class="modal-title fs-5" id="infoModalLabel">Modal title</h1>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
        </div>
    </div>
</div>
<script>
function getRelativeUrl(url)
{
    // Получаем полный URL текущей страницы
    var currentUrl = location.href;

    // Удаляем протокол и путь до корня сайта
    var relativeUrl = currentUrl.substring(currentUrl.indexOf('/', 8) + 1);

    // Добавляем заданный URL
    relativeUrl = relativeUrl + '/' + url;

    return relativeUrl;
}
$(document).ready(function(){
    $('#infoModal').on('show.bs.modal',function(e){
        console.log(e);
        console.log(e.relatedTarget);
        var td = $(e.relatedTarget).parent(td);
        console.log(td);
        console.log($(td).attr('data-payload'));
        if($(td).attr('data-payload')!==undefined)
        {
            console.log('payload');
            var payload = $(td).attr('data-payload');
            $('#infoModal .modal-body').html(payload);
        }
        else if($(td).attr('data-exception')!==undefined)
        {
            console.log('exception');
            var exception = $(td).attr('data-exception');
            $('#infoModal .modal-body').html(exception);
        }
    });
});
</script>

@endsection
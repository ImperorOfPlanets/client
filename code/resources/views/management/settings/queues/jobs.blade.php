@extends('template.template',[
	'title'=>'queues',
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush
@include('management.settings.queues.panel')

<table class='table table-bordered'>
    <thead>
        <tr>
            <th>
                ID
            </th>
            <th>
                Очередь
            </th>
            <th>
                Данные
            </th>
            <th>
                Попытки
            </th>
        </tr>
    </thead>
    <tbody>
        @foreach($queues as $queue)
        <tr>
            <td>
                {{$queue->id}}
            </td>
            <td>
                {{$queue->queue}}
            </td>
           <td data-payload="{{$queue->payload}}" class='text-center'>
                <button class="btn btn-success w-75" data-bs-toggle="modal" data-bs-target="#infoModal">Показать</button>
            </td>
            <td class='text-center'>
                {{$queue->attempts}}
            </td>
            <td class='text-center'>
                <form method='post' action="/management/settings/queues/{{$queue->id}}">
                    @csrf
                    @method('delete')
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
        <div class="modal-body text-wrap"></div>
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
function syntaxHighlight(json)
{
    if (typeof json != 'string') {
         json = JSON.stringify(json, undefined,20);
    }
    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
        var cls = 'number';
        if (/^"/.test(match)) {
            if (/:$/.test(match)) {
                cls = 'key';
            } else {
                cls = 'string';
            }
        } else if (/true|false/.test(match)) {
            cls = 'boolean';
        } else if (/null/.test(match)) {
            cls = 'null';
        }
        return '<span class="' + cls + '">' + match + '</span>';
    });
}
$(document).ready(function(){
    $('#infoModal').on('show.bs.modal',function(e){
        var td = $(e.relatedTarget).parent(td);
        if($(td).attr('data-payload')!==undefined)
        {
            var payload = $(td).attr('data-payload');
            console.log(syntaxHighlight(payload));
            //Проверяем на json
            var data = JSON.parse(payload);
            $('#infoModal .modal-body').html(syntaxHighlight(payload));
 //           console.log(data);
            
//            $.each(data, function(i, data){
//                console.log(i,data);
//               //$('#infoModal .modal-body').append('<li>dataid: '+data.id+'</li>');
//            });
            //$('#infoModal .modal-body').text(payload);
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
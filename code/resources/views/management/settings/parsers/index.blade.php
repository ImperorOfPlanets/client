@extends('template.template',[
	'title'=>'Парсеры'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush
<a href="/management/settings/parsers/create">Создать парсер</a>
<table class='table table-bordered'>
    <thead>
        <tr>
            <td>
                ID
            </td>
            <td>
                Название
            </td>
            <td>
                URL
            </td>
            <td>
                Кнопки
            </td>
        </tr>
    </thead>
    <tbody>
        @isset($parsers)
            @foreach ($parsers as $parser)
            <tr>
                <td>
                    {{$parser->id}}
                </td>
                <td>
                    {{$parser->propertyById(1)->pivot->value ?? 'БЕЗ НАЗВАНИЯ'}}
                </td>
                <td>
                    URL
                </td>
                <td>
                    <a href="/management/settings/parsers/{{$parser->id}}/edit">Редактировать</a>
                </td>
            </tr>
            @endforeach
        @endisset

    </tbody>
</table>
@endsection
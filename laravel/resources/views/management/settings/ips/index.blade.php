@extends('template.template',[
	'title'=>'Servers'
])
@section('content')
    @push('sidebar') @include('management.sidebar') @endpush
    <button data-action="addJob" class='form-control btn btn-success mb-1'>Обновить все слова</button>
    <table class="table table-bordered table-hover text-center table-responsive">
        <thead>
            <tr>
                <td>Название</td>
                <td>IP</td>
                <td>Тип сервера</td>
            </tr>
        </thead>
        <tbody>
        @foreach($ips as $ip)
            <tr>
                <td class="align-middle">
                    {{$ip->propertys()->propertyById(1)->pivot->value}}
                </td>
                <td class="align-middle">
                    {{$ip->propertys()->propertyById(82)->pivot->value ?? ''}}
                </td>
                <td data-params='{{$key->params}}'>
                    {{$ip->propertys()->propertyById(107)->pivot->value ?? ''}}
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection

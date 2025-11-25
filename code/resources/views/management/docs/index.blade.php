@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush

<a href="{{route('docs.create')}}">Создать документ</a>

<table class='table w-full border-collapse border border-slate-400 align-middle text-center mt-2'>
	<thead>
		<tr>
			<td class="border border-slate-300">ID объекта</td>
			<td class="border border-slate-300">Название документа</td>
			<td class="border border-slate-300">Кнопки</td>
		</tr>
	</thead>
	<tbody>
		@foreach($docs as $doc)
		<tr>
			<td class="border border-slate-300">{{$doc->id}}</td>
			<td class="border border-slate-300">{{$doc->propertyByID(1)->pivot->value ?? 'Без названия'}}</td>
			<td class="border border-slate-300">
				<a href="{{route('docs.edit',['doc'=>$doc->id])}}" target="_blank">Перейти на страницу</a>
			</td>
		</tr>
		@endforeach
	</tbody>
</table>
@endsection
@extends('template.template',[
	'title'=>'Categories'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush
<a href="{{route('categories.create')}}">Добавить категорию</a>
<a href="{{route('categories.show',['category'=>'tree'])}}">Показать дерево</a>
<table class='table w-full border-collapse border border-slate-400 align-middle text-center mt-2'>
	<thead>
		<tr>
			<td class="border border-slate-300">ID категории</td>
			<td class="border border-slate-300">Название</td>
			<td class="border border-slate-300">Кнопки</td>
		</tr>
	</thead>
	<tbody>
		@foreach($categories as $category)
		<tr>
			<td class="border border-slate-300">{{$category->id}}</td>
			<td class="border border-slate-300">{{$category->propertyByID(1)->pivot->value ?? 'Без названия'}}</td>
			<td class="border border-slate-300">
				<a href="{{route('categories.edit',['category'=>$category->id])}}" target="_blank">Редактировать</a>
			</td>
		</tr>
		@endforeach
	</tbody>
</table>
@endsection
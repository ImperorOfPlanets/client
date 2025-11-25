@extends('template.template',[
	'title'=>'Записи'
])
@section('content')
    @push('sidebar') @include('management.sidebar') @endpush
	<a href="{{route('posts.create')}}">Создать запись</a>
	@isset($posts)
		<table class='table w-full border-collapse border border-slate-400 align-middle text-center mt-2'>
			<thead>
				<tr>
					<td class="border border-slate-300">ID Записи</td>
					<td class="border border-slate-300">Название записи</td>
					<td class="border border-slate-300">Редактировать</td>
				</tr>
			</thead>
			<tbody>
				@foreach($posts as $post)
				<tr>
					<td class="border border-slate-300">{{$post->id}}</td>
					<td class="border border-slate-300">{{$post->propertyByID(1)->pivot->value ?? 'Без названия'}}</td>
					<td class="border border-slate-300">
						<a href="{{route('posts.edit',['post'=>$post->id])}}" target="_blank">Редактировать</a>
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	@else
		Записей нет
	@endif
    </table>
@endsection
@extends('template.template',[
	'title'=>'Статусы платежей'
])
@section('content')
    @push('sidebar') @include('management.sidebar') @endpush
	@isset($statuses)
		<table class='table w-full border-collapse border border-slate-400 align-middle text-center mt-2'>
			<thead>
				<tr>
					<td class="border border-slate-300">ID статуса</td>
					<td class="border border-slate-300">Текст</td>
                    <td class="border border-slate-300">Описание</td>
				</tr>
			</thead>
			<tbody>
				@foreach($statuses as $status)
				<tr>
					<td class="border border-slate-300">{{$status->id}}</td>
					<td class="border border-slate-300">{{$status->propertyByID(1)->pivot->value}}</td>
					<td class="border border-slate-300">{{$status->propertyByID(125)->pivot->value}}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	@else
		Записей нет
	@endif
    </table>
@endsection
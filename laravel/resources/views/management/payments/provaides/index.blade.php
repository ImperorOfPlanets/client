@extends('template.template',[
	'title'=>'Платежные системы'
])
@section('content')
    @push('sidebar') @include('management.sidebar') @endpush
	@isset($provaiders)
		<table class='table w-full border-collapse border border-slate-400 align-middle text-center mt-2'>
			<thead>
				<tr>
					<td class="border border-slate-300">ID Системы</td>
					<td class="border border-slate-300">Состояние</td>
					<td class="border border-slate-300">Редактировать</td>
				</tr>
			</thead>
			<tbody>
				@foreach($provaiders as $provaider)
				<tr>
					<td class="border border-slate-300">{{$provaider->id}}</td>
					<td class="border border-slate-300">{{$provaider->propertyByID(1)->pivot->value ?? 'Без названия'}}</td>
					<td class="border border-slate-300">
						<a href="/management/payments/provaiders/{{$provaider->id}}/edit" target="_blank">Редактировать</a>
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
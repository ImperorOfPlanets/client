@extends('template.template',[
	'title'=>'Платежи'
])
@section('content')
    @push('sidebar') @include('management.sidebar') @endpush
<div class="alert alert-danger" role="alert">
	По вопросам добавления платехных систем и валют обращаитьтся к разработчикам. Не бесплатно! Мы с голоду уже умираем..
</div>
	@isset($currencys)
		<table class='table w-full border-collapse border border-slate-400 align-middle text-center mt-2'>
			<thead>
				<tr>
					<td class="border border-slate-300">ID Валюты</td>
					<td class="border border-slate-300">Название</td>
				</tr>
			</thead>
			<tbody>
				@foreach($currencys as $currency)
				<tr>
					<td class="border border-slate-300">{{$currency->id}}</td>
					<td class="border border-slate-300">{{$currency->propertyByID(1)->pivot->value ?? 'Без названия'}}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	@else
		Записей нет
	@endif
    </table>
@endsection
@extends('template.template',[
	'title'=>'Платежи'
])
@section('content')
    @push('sidebar') @include('management.sidebar') @endpush
	<a href="/management/payments/payments/create">Создать ссылку на оплату</a>
	@isset($payments)
		<table class='table w-full border-collapse border border-slate-400 align-middle text-center mt-2'>
			<thead>
				<tr>
					<td class="border border-slate-300">ID Платежа</td>
					<td class="border border-slate-300">Платежная система</td>
					<td class="border border-slate-300">Сумма</td>
					<td class="border border-slate-300">Валюта</td>
					<td class="border border-slate-300">Статус</td>
					<td class="border border-slate-300">Описание статуса</td>
					<td class="border border-slate-300">Редактировать</td>
				</tr>
			</thead>
			<tbody>
				@foreach($payments as $payment)
				<tr>
					<td class="border border-slate-300">{{$payment->id}}</td>
					<td class="border border-slate-300">{{\App\Models\Payments\ProvaidersModel::find($payment->propertyByID(122)->pivot->value)->propertyById(1)->pivot->value}}</td>
					<td class="border border-slate-300">{{$payment->propertyByID(120)->pivot->value}}</td>
					<td class="border border-slate-300">{{\App\Models\Payments\CurrencysModel::find($payment->propertyByID(121)->pivot->value)->propertyById(1)->pivot->value}}</td>
					<td class="border border-slate-300">{{$statuses->where('id',is_null($payment->status) ? 50 : $payment->status )->first()->propertyById(1)->pivot->value}}</td>
					<td class="border border-slate-300">{{$statuses->where('id',is_null($payment->status) ? 50 : $payment->status )->first()->propertyById(125)->pivot->value}}</td>
					<td class="border border-slate-300">
						<a href="/management/payments/payments/{{$payment->id}}/edit" target="_blank">Редактировать</a>
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
@extends('template.template',[
	'title'=>'Orders'
])
@section('content')
    @push('sidebar') @include('management.sidebar') @endpush
	<a href="{{route('orders.create')}}">Добавить заказ</a>
	<br />
	@isset($orders)
		<table class='table w-full border-collapse border border-slate-400 align-middle text-center mt-2'>
			<thead>
				<tr>
					<td class="border border-slate-300">ID заказ</td>
					<td class="border border-slate-300">Редактировать</td>
				</tr>
			</thead>
			<tbody>
				@foreach($orders as $order)
				<tr>
					<td class="border border-slate-300">{{$order->id}}</td>
					<td class="border border-slate-300">
						<a href="{{route('order.edit',['order'=>$order->id])}}" target="_blank">Редактировать</a>
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	@else
		Заказов нет
	@endif
@endsection
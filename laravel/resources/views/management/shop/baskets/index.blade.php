@extends('template.template',[
	'title'=>'Baskets'
])
@section('content')
    @push('sidebar') @include('management.sidebar') @endpush
	@isset($baskets)
		<table class='table w-full border-collapse border border-slate-400 align-middle text-center mt-2'>
			<thead>
				<tr>
					<td class="border border-slate-300">ID Корзины</td>
					<td class="border border-slate-300">Наличие в корзине</td>
					<td class="border border-slate-300">Смотреть</td>
				</tr>
			</thead>
			<tbody>
				@foreach($baskets as $basket)
				<tr>
					<td class="border border-slate-300">{{$basket->id}}</td>
					<td class="border border-slate-300">{{$basket->propertyByID(1)->pivot->value ?? 'Без названия'}}</td>
					<td class="border border-slate-300">
						<a href="/management/shop/basket/{{$basket->id}}/edit" target="_blank">Редактировать</a>
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	@else
		Корзины отсуствуют
	@endif
@endsection
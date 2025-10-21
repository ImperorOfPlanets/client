@extends('template.template',[
	'title'=>'Products'
])
@section('content')
	@push('sidebar') @include('management.sidebar') @endpush
	<a href="{{route('m.shop.products.create')}}">Добавить продукт</a>
	<br />
	@isset($products)
		<table class='table w-full border-collapse border border-slate-400 align-middle text-center mt-2'>
			<thead>
				<tr>
					<td class="border border-slate-300">ID продукта</td>
					<td class="border border-slate-300">Название документа</td>
					<td class="border border-slate-300">Редактировать</td>
				</tr>
			</thead>
			<tbody>
				@foreach($products as $product)
				<tr>
					<td class="border border-slate-300">{{$product->id}}</td>
					<td class="border border-slate-300">{{$product->propertyByID(1)->pivot->value ?? 'Без названия'}}</td>
					<td class="border border-slate-300">
						<a href="{{route('m.shop.products.edit',['product'=>$product->id])}}" target="_blank">Редактировать</a>
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	@else
		Продуктов нет
	@endif
@endsection
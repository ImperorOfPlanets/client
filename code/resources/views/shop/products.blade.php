<table class="table table-bordered text-center">
	<thead>
		<tr>
			<td>ID</td>
			<td>Наименование</td>
			<td>Количество</td>
			<td>Цена</td>
			<td>Сумма</td>
		</tr>
	</thead>
	<tbody>
		@foreach($basket as $product)
		<tr>
			<td>
				{{$product['id']}}
			</td>
			<td>
				{{$product->propertyByID(1)->pivot->value ?? 'Без названия'}}
			</td>
			<td>
				{{$product->count}}
			</td>
			<td>
				{{$product->propertyByID(110)->pivot->value}}
			</td>
			<td>
				{{$product->propertyByID(110)->pivot->value * $product->count}}
			</td>
		</tr>
		@endforeach
	</tbody>
</table>
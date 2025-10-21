<div data-product-id="{{$product->id}}" class="card p-1 w-100">
		<img src ="{{$product->propertyByID(11)->pivot->value ?? '/img/shop/noimage.webp'}}" />
	<hr />
	<div class="card-body">
		<h5 class="card-title">{{$product->propertyByID(1)->pivot->value ?? 'Без названия'}}
		<p class="card-text">Описание</p>
	</div>
	<div class="card-footer row m-0 align-middle">
		@if(is_null($product->propertyByID(110)) || $product->propertyByID(110)->pivot->value=='')
			<div class='text-center'>
				<button class='btn btn-warning' data-action="sendRequestForBuy">ОТПРАВИТЬ ЗАПРОС НА ЗАКАЗ</button>
			</div>
		@else
			<div class="col d-flex align-items-center">
				{{$product->propertyByID(110)->pivot->value ?? 'Цена отсутствует'}}
			</div>
			<div class="col align-middle text-center">
				Количество
				<div class="input-group mb-3">
					<span class="input-group-text" data-action="reCount">-</span>
					<input type="text" class="form-control" data-count value="1" />
					<span class="input-group-text" data-action="addCount">+</span>
				</div>
			</div>
			<div class="col text-end d-flex align-items-center text-right pe-0">
				<a href="#" class="btn btn-primary" data-action='addInCart'>Купить</a>
			</div>
		@endif
	</div>
</div>
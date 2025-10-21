@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
<div class='text-center fs-2 fw-bold'>
Корзина
</div>
<ul class="nav nav-tabs" id="myTab" role="tablist">
	<li class="nav-item" role="presentation">
		<button class="nav-link active" id="products-tab" data-bs-toggle="tab" data-bs-target="#products-tab-pane" type="button" role="tab" aria-controls="products-tab-pane" aria-selected="false" tabindex="-1">Товары</button>
	</li>
	<li class="nav-item" role="presentation">
		<button class="nav-link" id="delivery-tab" data-bs-toggle="tab" data-bs-target="#delivery-tab-pane" type="button" role="tab" aria-controls="delivery-tab-pane" aria-selected="true">Адрес и Доставка</button>
	</li>
	<li class="nav-item" role="presentation">
		<button class="nav-link" id="pay-tab" data-bs-toggle="tab" data-bs-target="#pay-tab-pane" type="button" role="tab" aria-controls="pay-tab-pane" aria-selected="true">Оплата</button>
	</li>
</ul>
<div class="tab-content" id="myTabContent">
	<div class="tab-pane fade show active" id="products-tab-pane" role="tabpanel" aria-labelledby="products-tab" tabindex="0">
		@include('shop.products')
	</div>
	<div class="tab-pane fade" id="delivery-tab-pane" role="tabpanel" aria-labelledby="delivery-tab" tabindex="0">
		@include('shop.delivery')
	</div>
	<div class="tab-pane fade" id="pay-tab-pane" role="tabpanel" aria-labelledby="pay-tab" tabindex="0">
		@include('shop.pay')
	</div>
</div>
@endsection
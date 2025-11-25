<div class="row">
	@foreach($products as $product)
		<div class="d-flex justify-content-center col-sm-12 col-md-6 col-lg-3 mb-3 mb-sm-0 p-2">
			@include('shop.preview')
		</div>
	@endforeach
</div>
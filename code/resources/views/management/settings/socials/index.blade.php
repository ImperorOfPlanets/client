@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
    @push('sidebar') @include('management.sidebar') @endpush
	@foreach ($socials as $social)
	<div class="card m-1 w-100" data-id="{{$social->id}}">
		<h5 class="card-header">{{$social->propertyById(1)->pivot->value}}</h5>
		<div class="card-body">
		</div>
		<div class="card-footer text-body-secondary d-grid">
			@if($social)
				<button type="button" class="btn btn-primary mb-1" data-bs-toggle="modal" data-bs-target="#modal{{$social->id}}">
					Настройки
				</button>
				<button type="button" class="btn btn-danger">
					Удалить
				</button>
			@else
				<button data-action='installSoc' data-send="install">Установить</button>
			@endif
		</div>
	</div>
	<div class="modal fade" id="modal{{$social->id}}" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
				<h1 class="modal-title fs-5" id="exampleModalLabel">{{$social->propertyById(1)->pivot->value}}</h1>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					@if(is_null($social->propertyById(100)))
						Не указанны настройки для изменений
					@else
						@php
							$propertysForEdit = json_decode($social->propertyById(100)->pivot->value);
						@endphp
						@foreach($propertysForEdit as $property)
							<div class="form-floating mb-1">
								<label for="inp{{$property}}" >{{$social->propertyById($property)->name}}</label>
								<input id="inp{{$property}}" class="form-control" type="text" property-id="{{$property}}" value="{{$social->propertyById($property)->pivot->value}}" />
							</div>
						@endforeach
					@endif
				</div>
			</div>
		</div>
	</div>
	@endforeach
	<script>
	$(document).ready(function() {
		console.log('>>>>>>>>>>>>>>>>>> $(window).on(load) - management.settings.socals.index.blade <<<<<<<<<<<<<<<<<<<<<');

		/*$('body').on('click','button[data-action]',function(){
			console.log(this);
			var action = $(this).attr('data-action');
			alert(action);
		});*/
	});
	</script>
@endsection

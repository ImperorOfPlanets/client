@extends('template.template',[
	'title'=>'Commands',
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush
<div class="accordion accordion-flush" id="accordionFlushExample">
	<div class="accordion-item">
		<h2 class="accordion-header">
			<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
			Основные настройки
			</button>
		</h2>
		<div id="flush-collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
			<div class="accordion-body">
				@include('management.assistant.settings.basic')
			</div>
		</div>
	</div>
	<div class="accordion-item">
		<h2 class="accordion-header">
			<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseTwo" aria-expanded="false" aria-controls="flush-collapseTwo">
				Социальные сети
			</button>
		</h2>
		<div id="flush-collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionTwo">
			<div class="accordion-body">
				@include('management.assistant.settings.socials')
			</div>
		</div>
	</div>
</div>
@endsection
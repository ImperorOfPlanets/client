@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
    @push('sidebar') @include('management.sidebar') @endpush
	<div class="accordion accordion-flush" id="accordionFlushExample">
		<div class="accordion-item">
			<h2 class="accordion-header">
				<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseColors" aria-expanded="false" aria-controls="flush-collapseColors">
				Цвета
				</button>
			</h2>
			<div id="flush-collapseColors" class="accordion-collapse collapse" data-bs-parent="#accordionFlushColors">
				<div class="accordion-body">
					@include('management.settings.basic.colors')
				</div>
			</div>
		</div>
		<div class="accordion-item">
			<h2 class="accordion-header">
				<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseLogo" aria-expanded="false" aria-controls="flush-collapseLogo">
					Логотип
				</button>
			</h2>
			<div id="flush-collapseLogo" class="accordion-collapse collapse" data-bs-parent="#accordionFlushLogo">
				<div class="accordion-body">
					@include('management.settings.basic.logo')
				</div>
			</div>
		</div>
		<div class="accordion-item">
			<h2 class="accordion-header">
				<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseTWorks" aria-expanded="false" aria-controls="flush-collapseTWorks">
					Время технических работ
				</button>
			</h2>
			<div id="flush-collapseTWorks" class="accordion-collapse collapse" data-bs-parent="#accordionFlushTWorks">
				<div class="accordion-body">
					@include('management.settings.basic.technical-works')
				</div>
			</div>
		</div>
	</div>
@endsection

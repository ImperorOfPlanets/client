@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
    @push('sidebar') @include('management.sidebar') @endpush
	<div class="card text-center">
		<div class="card-header">
			Группа Telegramm
		</div>
		<div class="card-body d-grid gap-2">
		</div>
	</div>
	<div class="card text-center">
		<div class="card-header">
			В контакте
		</div>
		<div class="card-body d-grid gap-2">
		</div>
	</div>
@endsection
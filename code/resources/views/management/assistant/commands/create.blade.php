@extends('template.template',[
	'title'=>'Create Command',
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush
<form action='{{route('m.assistant.commands.store')}}' method='post'>
	@csrf
	<div class='p-2'>
		Название
		<input type='text' name='command' class='form-control' value="">
	</div>

	<div class='p-2'>
		<button class='btn btn-success btn-block'>Сохранить</button>
	</div>
</form>

@endsection
@extends('template.template',[
	'title'=>'Записи'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush
<a href="{{route('posts.create')}}">Создать запись</a>
<div class="card m-1 w-100">
	<form action="{{route('posts.store')}}" method='post'>
		@csrf
		<h5 class="card-header">Название</h5>
		<div class="card-body"> 
			<input type="text" name="name" class='form-control' />
		</div>
		<div class="card-footer text-body-secondary d-grid">
			<button data-send="createDoc">Создать</button>
		</div>
	</form>
</div>
@endsection
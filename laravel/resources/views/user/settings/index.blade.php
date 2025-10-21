@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
<div class="dropdown text-center mt-1">
	<button class="btn btn-secondary dropdown-toggle w-50" type="button" data-bs-toggle="dropdown" aria-expanded="false">
		Выберите раздел
	</button>
	<ul class="dropdown-menu">
		<li><a class="dropdown-item" href="#">Безопасность</a></li>
		<li><a class="dropdown-item" href="#">Социальные сети</a></li>
	</ul>
</div>
@endsection
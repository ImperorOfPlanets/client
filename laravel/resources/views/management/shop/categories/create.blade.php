@extends('template.template',[
	'title'=>'Categories'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush
<form action="{{route('categories.store')}}" method='post'>
	@csrf
	<select name='parent' class="form-control">
		<option value="0">Выберите родителя</option>
		@foreach($categories as $category)
			<option value="{{$category->id}}">{{$category->propertyByID(1)->pivot->value}}</option>
		@endforeach
	</select>
	Название
	<input type="text" name="name" class='form-control' />
	<button>Создать</button>
</form>	
@endsection
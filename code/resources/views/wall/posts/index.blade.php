@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
	@push('scripts')
		@vite(['resources/js/posts.js'])
	@endpush
	@include('wall.posts.create')
	@isset($posts)
		@foreach($posts as $post)
			@include('wall.posts.post',['post'=>$post])
		@endforeach
	@endisset
	@include('wall.posts.bottom')
@endsection
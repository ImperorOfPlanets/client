@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
	@push('sidebar') @include('management.sidebar') @endpush
@endsection

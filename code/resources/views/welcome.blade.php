@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
	@auth
	@endauth
	@guest
		@php
			$state = \Str::random(16);
			session()->put('state',$state);
			$queryAuth = http_build_query(array(
				'client_id' => env('OAUTH_CLIENT_ID'),
				'redirect_uri' => env('OAUTH_REDIRECT_URI'),
				'response_type' => 'code',
				'scope' => '',
				'state'=>$state
			));
			view()->share('queryAuth',$queryAuth)
		@endphp
		<a href='https://myidon.site/oauth/authorize?{{$queryAuth}}'>{{__('Sign in with My ID on Site')}}</a>
	@endguest
@endsection
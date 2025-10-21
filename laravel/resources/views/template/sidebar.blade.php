@stack('sidebar')
@guest
	@php
		if(!isset($queryAuth))
		{
			$state = \Str::random(16);
			session()->put('state',$state);
			$queryAuth = http_build_query(array(
				'client_id' => env('OAUTH_CLIENT_ID'),
				'redirect_uri' => env('OAUTH_REDIRECT_URI'),
				'response_type' => 'code',
				'scope' => '',
				'state'=>$state
			));
		}
	@endphp
	<li>
		<a href="https://myidon.site/oauth/authorize?{{$queryAuth}}">
			{{__('Login')}}
		</a>
	</li>
@endguest
@if(session()->has('roles'))
	<li>
		<a href="/management">
			{{__('Management')}}
		</a>
	</li>
@endif
<li>
	<a href="/wall">
		{{__('Wall')}}
	</a>
</li>

<li>
	<a href="/shop">
		{{__('Shop')}}
	</a>
	<div id="tree"></div>
</li>

@isset($sideLinks)
@foreach($sideLinks as $link)
<li>
	<a href="{{$link->href}}">
		{{$link->text}}
	</a>
</li>
@endforeach
@endisset
<li>
	<a href="/assistant">
		{{__('Assistant')}}
	</a>
</li>
@auth
<li>
	<a href="/user/settings">
		{{__('Settings')}}
	</a>
</li>
<!--<li>
	<a href="/balance">
		{{__('Balance')}}
	</a>
</li>-->
@if(session()->has('management'))
	<li>
		<a href="/management">
			{{__('Management panel')}}
		</a>
	</li>
@endif
<li>
	<a href="/logout">
		{{ __('Log Out') }}
	</a>
</li>
@endauth
<!--<li>
	<a href="/feedback">
		{{ __('Feedback') }}
	</a>
</li>-->
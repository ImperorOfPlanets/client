@extends('template.template', ['title' => 'Welcome'])

@section('content')
    @auth
        <!-- Авторизован -->
    @endauth

    @guest
        @php
            $host = request()->getHost();
            $port = (int) request()->getPort();

            // Определяем, локальная ли это разработка
            if ($host === 'localhost') {
                $clientId = env('LOCAL_OAUTH_CLIENT_ID');
                $clientSecret = env('LOCAL_OAUTH_SECRET');
                $redirectUri = 'https://localhost/auth/callback';
            } else {
                // Используем продакшен-настройки
                $clientId = env('OAUTH_CLIENT_ID');
                $clientSecret = env('OAUTH_SECRET');
                $redirectUri = env('OAUTH_REDIRECT_URI'); // из .env
            }

            $state = \Str::random(16);

            // Сохраняем всё в сессии для callback
            session()->put('state', $state);
            session()->put('oauth_redirect_uri', $redirectUri);
            session()->put('oauth_client_id', $clientId);
            session()->put('oauth_client_secret', $clientSecret);

            $queryAuth = http_build_query([
                'client_id'     => $clientId,
                'redirect_uri'  => $redirectUri,
                'response_type' => 'code',
                'scope'         => '',
                'state'         => $state
            ]);
        @endphp

        <a href="https://myidon.site/oauth/authorize?{{ $queryAuth }}">
            {{ __('Sign in with My ID on Site') }}
        </a>
    @endguest
@endsection
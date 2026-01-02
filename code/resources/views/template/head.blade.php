<head>
<title>{{$title ?? ''}}</title>
<meta charset="utf-8">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="viewport" content="width=device-width, initial-scale=1">
<META HTTP-EQUIV="CACHE-CONTROL" CONTENT="NO-CACHE">
<style>
	/* Стили для загрузчика */
	.loader {
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background-color: white;
		display: flex;
		justify-content: center;
		align-items: center;
		z-index: 9999;
		flex-direction: column;
	}
	.loader-content {
		text-align: center;
		width: 100%; /* Ширина на весь экран */
	}
	.loader-text {
		font-size: 24px;
		font-weight: bold;
		color: #333;
	}
	.loader-animation {
		width: 100px; /* Установите размер анимации */
		height: 100px;
	}
	.loader-log {
		font-size: 14px;
		color: #555;
		width: 100%; /* На всю ширину экрана */
		height: auto; /* Высота зависит от контента */
		text-align: left; /* Выравнивание текста по левому краю */
		border-top: 1px solid #ddd;
		padding: 0; /* Убираем отступы */
		background-color: #f9f9f9; /* Светло-серый фон для лога */
	}
</style>
<script src="/js/jquery-3.6.4.min.js" type="text/javascript"></script>
<script src="/js/js.cookie.min.js" type="text/javascript"></script>
<!-- PWA -->
<link rel="manifest" href="/manifest.json">
<!-- Files -->
<link rel="manifest" href="/build/manifest.json">
<!-- Временно вместо @vite для CSS -->
<link href="{{ asset('build/assets/app.css') }}" rel="stylesheet">
@vite([	
	'resources/css/app.css',
	'resources/js/app.js',
	'resources/js/pwa.js',
	'resources/js/sidebar.js'
	//'resources/js/intervals.js',
	//'resources/js/events.js',
	//'resources/js/files.js',
	//'resources/js/preloader.js',
	//'resources/js/workers.js',
	//'resources/js/worker.cache.js',
	//'resources/js/worker.push.js'
])
@stack('styles')
@stack('scripts')
</head>
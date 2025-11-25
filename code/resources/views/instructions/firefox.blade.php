@extends('template.template',[
	'title'=>'Инструкция по установке PWA в Firefox'
])
@section('content')
    <h1>Ошибка: PWA не поддерживается в Firefox</h1>
    <p>Ваш браузер (Firefox) не поддерживает технологию PWA (Progressive Web App). Для корректной работы с этим приложением рекомендуется использовать браузер, который поддерживает PWA.</p>
    <p>Вы можете использовать один из следующих браузеров:</p>
    <ul class="browser-list">
        <li><a href="https://www.google.com/chrome/" target="_blank">Google Chrome</a></li>
        <li><a href="https://www.microsoft.com/edge" target="_blank">Microsoft Edge</a></li>
    </ul>
@endsection
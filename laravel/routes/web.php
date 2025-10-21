<?php

use Illuminate\Support\Facades\Route;

\Illuminate\Support\Facades\URL::forceScheme('https');

Route::get('/', function () {
    return view('welcome');
});

require __DIR__.'/project.php';
require __DIR__.'/management.php';
require __DIR__.'/control.php';

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Auth;
use App\Services\Auth\UserProvider;
use App\Services\Auth\MyidonGuard;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
      //Добавляем провайдвера - поставщика пользователей
      Auth::provider('myidon', function ($app, array $config) {
        // Return an instance of Illuminate\Contracts\Auth\UserProvider...
        return new UserProvider();
      });
      Auth::extend('myidon', function ($app, $name, array $config) {
        return new MyidonGuard(Auth::createUserProvider($config['provider']), $app->make('request'));
      });
    }
}

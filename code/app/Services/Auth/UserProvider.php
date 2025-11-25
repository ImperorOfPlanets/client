<?php
namespace App\Services\Auth;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider as AuthProvider;
use App\Models\User;

class UserProvider implements AuthProvider
{
	public function retrieveById($identifier): Authenticatable
	{
		/*
		Функция retrieveById обычно получает ключ, представляющий пользователя, например автоматически увеличивающийся идентификатор
		из базы данных MySQL. Реализация Authenticatable, соответствующая идентификатору, должна быть получена и возвращена методом.
		*/
		//\Debugbar::info($identifier);
		return new User;
        //return 777;
	}

	public function retrieveByToken($identifier, $token)
	{
		/*
		Функция retrieveByToken извлекает пользователя по его уникальному $identifier и «запомнить меня» $token,
		обычно хранящемуся в столбце базы данных, таком как remember_token. Как и в предыдущем методе,
		Authenticatable этот метод должен возвращать реализацию с совпадающим значением токена.
		*/
		//Debugbar::info($identifier);
		//dd($identifier);
	}

	public function updateRememberToken(Authenticatable $user, $token)
	{
		/*
		Метод updateRememberToken обновляет $user экземпляр remember_token новым файлом $token.
		Новый токен назначается пользователям при успешной попытке аутентификации «запомнить меня» или при выходе пользователя из системы.
		*/
		//\Debugbar::info($user);
		//\Debugbar::info($token);
		//dd($user);
	}

	public function retrieveByCredentials(array $credentials):Authenticatable
	{
		/*
		Метод retrieveByCredentials получает массив учетных данных, переданных Auth::attempt методу при попытке аутентификации в приложении.
		Затем метод должен «запросить» базовое постоянное хранилище для пользователя, соответствующего этим учетным данным.
		Как правило, этот метод запускает запрос с условием «где», который ищет запись пользователя с «именем пользователя»,
		совпадающим со значением $credentials['username']. Метод должен возвращать реализацию Authenticatable.
		Этот метод не должен пытаться выполнить проверку пароля или аутентификацию.
		*/
		//return true;
		//dd($credentials);
		\Debugbar::info($credentials);
	}

	public function validateCredentials(Authenticatable $user, array $credentials)
	{
		/*
		Метод validateCredentials должен сравнить данный $user с $credentials для аутентификации пользователя.
		Например, этот метод обычно использует Hash::check метод для сравнения значения $user->getAuthPassword()со
		значением $credentials['password']. Этот метод должен возвращать true или false указывать, действителен ли пароль.
		*/
		//\Debugbar::info($user);
		//\Debugbar::info($credentials);
		//dd($user);
		return true;
	}

	public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false){;}
}
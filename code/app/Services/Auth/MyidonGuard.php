<?php
namespace App\Services\Auth;
 
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\User;

class MyidonGuard implements Guard
{
  protected $request;
  protected $provider;
  protected $user;
 
  /** 
* Create a new authentication guard. 
* 
* @param \Illuminate\Contracts\Auth\UserProvider $provider 
* @param \Illuminate\Http\Request $request 
* @return void 
*/
	public function __construct(UserProvider $provider, Request $request)
	{
		$this->request = $request;
		$this->provider = $provider;
		$this->user = $this->request->session()->get('user_id',false);
	}
 
    /**
     * Определите, прошел ли текущий пользователь проверку подлинности.
     *
     * @return bool
     */
    public function check(){
		return $this->user;
	}

    /**
     * Определите, является ли текущий пользователь гостем.
     *
     * @return bool
     */
    public function guest(){
		return !$this->user;
	}

    /**
     * Получение пользователя, прошедшего проверку подлинности.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user(){
		return new User;
	}

    /**
     * Получение идентификатора для текущего пользователя, прошедшего проверку подлинности.
     *
     * @return int|string|null
     */
    public function id(){
		//dd($this->request->session());
		if($this->user)
		{return $this->user;}
		else
		{return null;}
	}

    /**
     * Проверка учетных данных пользователя.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function validate(array $credentials = []){
		;
	}

    /**
     * Определите, имеет ли защита экземпляр пользователя.
     *
     * @return bool
     */
    public function hasUser(){
	}

    /**
     * Установка текущего пользователя.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return void
     */
    public function setUser(Authenticatable $user){
	}
}
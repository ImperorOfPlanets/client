<?php
namespace App\Jobs\ForControlProjects\CreateAccounts;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

use App\Helpers\Logs\Logs as Logator;
use App\Helpers\Control\API\OpenVPN;

class CreateVPN implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable;

	public $params = null;

	public $object = null;

    public $logator;

	public function __construct($params = null)
	{
		$this->params = $params;
    }

	public function handle()
	{
		//Передаем данные для авторизации
		$vpn = new OpenVPN('https://37.46.134.128',$this->params['login'],$this->params['password']);
		//Получаем список занятых адресов
		$vpn->auth();
		/*$response = $vpn->createUser([
			'login'=>$this->params['login'],
			'password'=>$this->params['password']
		]);
		dd($response);
		//$auth = $vpn->auth();
		$email = $isp->createEmail([
			'domain'=>$this->params['domain'],
			'login'=>$this->params['login'],
			'password'=>$this->params['password']
		]);*/
	}
}
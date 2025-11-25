<?php
namespace App\Jobs\ForControlProjects\CreateAccounts;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

use App\Helpers\Logs\Logs as Logator;
use App\Helpers\Control\API\ISPManagerAPI;

class CreateEmail implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable;

    public $logator;
	public $params;

	public function __construct($params = null)
	{
		$this->params = $params;
    }

	public function handle()
	{
		//Передаем данные для авторизации
		$isp = new ISPManagerAPI(
            env('ISPMANAGER_URL'),
            env('ISPMANAGER_LOGIN'),
            env('ISPMANAGER_PASSWORD')
        );
		//Создаем почту
		$auth = $isp->auth();
		$email = $isp->createEmail([
			'domain'=>$this->params['domain'],
			'login'=>$this->params['login'],
			'password'=>$this->params['password']
		]);
	}
}
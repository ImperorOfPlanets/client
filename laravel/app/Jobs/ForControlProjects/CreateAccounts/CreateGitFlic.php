<?php
namespace App\Jobs\ForControlProjects\CreateAccounts;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

use App\Helpers\Logs\Logs as Logator;
use App\Helpers\Control\API\GitFlic;

class CreateGitFlic implements ShouldQueue
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
		$gitflic = new GitFlic();
		//Получаем список занятых адресов
		$response = $gitflic->createUser($this->params);
		dd($response);
		//$auth = $vpn->auth();
		/*$email = $isp->createEmail([
			'domain'=>$this->params['domain'],
			'login'=>$this->params['login'],
			'password'=>$this->params['password']
		]);*/
	}
}
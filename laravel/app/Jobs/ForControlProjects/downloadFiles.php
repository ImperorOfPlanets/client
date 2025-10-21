<?php

namespace App\Jobs\ForControlProjects;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

use App\Models\Core\Objects;

use App\Helpers\Control\Ssh;

class downloadFiles implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
	//use Dispatchable, InteractsWithQueue, SerializesModels;

	public $params = null;
	public $object = null;
	public $ssh = null;

	public function __construct($params = null)
	{
		$this->params = $params;
    }

	public function handle()
	{
		$this->object = Objects::find($this->params['ids']);

		if($this->object instanceof Collection)
		{
			foreach($this->object as $obj)
			{
				$this->run($obj);
			}
		}
		else
		{
			$this->run($this->object);
		}
	}

	public function run($obj)
	{
		$this->ssh = new Ssh([
			'project_id'=>$obj->id
		]);
		//Если массив то отправляем соотвественно ключа все файлы
		if(is_array($this->params['myPath']))
		{
			foreach($this->params['myPath'] as $key=>$myPath)
			{
				if(is_array($myPath))
				{
					dd(777);
					
				}
				$this->ssh->downloadFile($this->params['myPath'][$key],$this->params['remotePath'][$key]);
			}
		}
		else
		{
			$this->ssh->downloadFile($this->params['myPath'],$this->params['remotePath']);
		}
	}
}
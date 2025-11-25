<?php

namespace App\Jobs\Posts;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Helpers\Telegram;
use App\Helpers\Vk;

class publicPost implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public $soc = null;
	public $params = null;
	/*
	[
		'soc'=>'Социальная сеть',
		'text'=>'Текст',
		'attachments'=>[
			['path'=>path_to_file]
		]
	]
	*/
	public function __construct($params)
	{
		$this->params = $params;
    }

	public function handle()
	{
		if($this->params['soc']=='vk')
		{
			$this->soc = new Vk;
		}
		elseif($this->params['soc']=='tg')
		{
			$this->soc = new Telegram;
		}
		$this->soc->publishPost($this->params);
	}
}
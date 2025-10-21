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

class runCommandOnProject implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public $params = null;

	public $object = null;

	public $connection = null;

	public function __construct($params = null)
	{
		$this->params = $params;
    }

	public function handle()
	{
		$this->object = Objects::find($this->params['ids']);

		//Если получили коллекцию то перебираем пригодится для запись файлов
		if($this->object instanceof Collection)
		{
			foreach($this->object as $obj)
			{
				$this->connection = ssh2_connect($obj->propertyById(31)->pivot->value,$obj->propertyById(32)->pivot->value);
				ssh2_auth_password($this->connection,$obj->propertyById(33)->pivot->value,$obj->propertyById(34)->pivot->value);

				$commandArtisan = 'cd '.$obj->propertyById(64)->pivot->value. ' && '.$obj->propertyById(37)->pivot->value.' artisan command:'.$this->params['command'];
				$streamArtisan = ssh2_exec($this->connection,$commandArtisan);
				stream_set_blocking($streamInstall, true);
				$stream_outArtisan = ssh2_fetch_stream($streamArtisan, SSH2_STREAM_STDIO);
				$resultArtisan = stream_get_contents($stream_outArtisan);
				echo $resultArtisan."\n";
			}
		}
		else
		{
			$this->connection = ssh2_connect($this->object->propertyById(31)->pivot->value,$this->object->propertyById(32)->pivot->value);
			ssh2_auth_password($this->connection,$this->object->propertyById(33)->pivot->value,$this->object->propertyById(34)->pivot->value);

			$commandArtisan = 'cd '.$this->object->propertyById(64)->pivot->value. ' && '.$this->object->propertyById(37)->pivot->value.' artisan command:'.$this->params['command'];
			$streamArtisan = ssh2_exec($this->connection,$commandArtisan);
			stream_set_blocking($streamArtisan, true);
			$stream_outArtisan = ssh2_fetch_stream($streamArtisan, SSH2_STREAM_STDIO);
			$resultArtisan = stream_get_contents($stream_outArtisan);
			echo $resultArtisan."\n";
		}
	}
}
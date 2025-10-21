<?php
namespace App\Jobs\Keywords;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use DB;
use Illuminate\Support\Facades\Schema;


use App\Models\Assistant\KeywordsModel;
use App\Models\Propertys;

use App\Helpers\Logs\Logs as Logator;

class GenKeywords implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	//Logs
	public $logator;

	//Название ключа объединяющих модель и модель
	public $forSearch = [
		'commands'=> 'App\Models\Assistant\CommandsModel',
		'products'=>  'App\Models\Shop\ProductsModel'
	];

	public $arrayKeywords = [];
	public function __construct()
	{
		//Логатор
		$this->logator = new Logator;

		$this->logator->setAuthor('GenKeywords');

		$this->logator->setType('success');
		$this->logator->setText('Запущена генерация ключевых слов');
		$this->logator->write();

		//Запускаем миграцию
		//$path = base_path("/storage/migrations/create_cacheKeywords.php");
		//\Artisan::call('migrate --path="/storage/migrations/create_cacheKeywords.php"');
    }



	public function handle()
	{
		$arrayKeywordsLogs = [];

		//Получаем все включенные
		$this->getAllKeywords();

		//Проверяем таблицу
		//$table->text('params');
		//$table->string('keyword', 32)->index('keyword');
		if(Schema::hasTable('keywords'))
		{
			echo "Очищаем таблицу\n";
			DB::table('keywords')->truncate();
		}
		else
		{
			DB::statement('CREATE TABLE keywords ( keyword VARCHAR(128) NOT NULL, params TEXT, PRIMARY KEY (keyword) ) ENGINE=INNODB');

		}
		foreach($this->arrayKeywords as $keyword=>$params)
		{
			DB::table('keywords')->insert([
				'keyword' => trim(mb_strtolower($keyword)),
				'params' => json_encode($params,JSON_UNESCAPED_SLASHES)
			]);
		}
	}
	

	//Получает все ключевые с моделей указаных
	public function getAllKeywords()
	{
		foreach($this->forSearch as  $key=>$model)
		{
			echo "Получаем - $key \n";

			//Получаем включенные объекты моделей
			$objectsModel = $model::with('propertys')
				->whereHas('propertys',function($q){
					$q
						->where('propertys.id',116)
						->where('value','true');
				})->get();
			echo 'Получено объектов - '.$objectsModel->count()."\n";

			//Перебираем модели
			foreach($objectsModel as $keyObject=>$object)
			{
				echo "Проверяем $key с ИД ".$object->id." \n";
				//Получаем ключевые слова комманд
				$keywordsObject = $object->propertyById(8)->pivot->value ?? null;

				//Удаляем без ключевых слов и пустышки
				if(is_null($keywordsObject) || $keywordsObject=='')
				{
					echo $object->id." Отсуствуют ключевые слова или там пусто. Элемент удален.\n";
					unset($objectsModel[$keyObject]);
					continue;
				}

				$explodedKeyWords = explode(',',$keywordsObject);
				foreach($explodedKeyWords as $keyKeyword=>$Keyword)
				{
					echo "Ключевое слово - $Keyword \n";
					if(strlen($Keyword)>1)
					{
						//Проверяем на существование ключевого слова
						if(!isset($this->arrayKeywords[$Keyword]))
						{
							$this->arrayKeywords[$Keyword]=[];
						}

						//Проверяем на существование комманд
						if(!isset($this->arrayKeywords[$Keyword][$key]))
						{
							$this->arrayKeywords[$Keyword][$key]=[];
						}
						
						//Добавляем ID объекта и действия
						if(!isset($this->arrayKeywords[$Keyword][$key][$object->id]))
						{
							//Получаем права
							$access = $object->propertyById(119)->pivot->value ?? null;
							$this->arrayKeywords[$Keyword][$key][$object->id]=$access;
						}
					}
				}
			}

		}
	}
}
<?php
namespace App\Jobs\Sync;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

use App\Helpers\Logs\Logs as Logator;

class ExportDB implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable;

    //Параметры
	public $params = null;

    //Логатор
    public $logator;

    //Экспортируемые таблицы
    public $exportTables = [];

    //Ключ таблицы
    public $keyTableControl = null;

    //Список таблиц обязательных для экспорта
    public $forExportSurely = [
		'cachekeywords',
		'failed_jobs',
		'files',
		'jobs',
		'keywords',
		'messages',
		'processes',
		'sessions',
		'updates'
	];

	public function handle()
	{
        //Логатор
        $this->logator = new Logator;

        $this->logator->setAuthor('Sync - ExportDB');
        $this->logator->setText('Запущен процесс экспорта БД');
        $this->logator->write();

		$tables = DB::select('SHOW TABLES');

        $this->logator->setText("Количество таблиц ".count($tables));
        $this->logator->write();

		foreach($tables as $tableKey=>$table)
		{
			//Получаем  ключ
			if(is_null($this->keyTableControl))
			{
				$this->keyTableControl = key($table);
			}

			$tableName = $table->{$this->keyTableControl};
			$this->exportTables[] = $tableName;
			/*if(in_array($tableName,$this->forExportSurely))
			{
				echo "Таблица: ".$tableName. " экпортируем\n";
				$this->exportTables[] = $tableName;
			}
			else
			{
				$tableNameExploded = explode('_',$tableName);

				//Последний элемент
				$lastElement = end($tableNameExploded);

				if(in_array($lastElement,array('','propertys')))
				{
					echo "Таблица: ".$tableName. " экпортируем\n";

					$this->exportTables[] = $tableName;
					
				}
				else
				{
					echo "Таблица: ".$tableName. " пропускаем\n";
				}
			}*/
		}

		$this->our_backup_database();

        $this->logator->setAuthor('Sync - ExportDB');
        $this->logator->setText('Процесс экспорта БД завершен');
        $this->logator->write();
	}

    public function our_backup_database()
    {
		//ENTER THE RELEVANT INFO BELOW
		$mysqlHostName      = env('DB_HOST');
		$mysqlUserName      = env('DB_USERNAME');
		$mysqlPassword      = env('DB_PASSWORD');
		$DbName             = env('DB_DATABASE');

		$connect = new \PDO("mysql:host=$mysqlHostName;dbname=$DbName;charset=utf8", "$mysqlUserName", "$mysqlPassword",array(\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));

		$output = "SET FOREIGN_KEY_CHECKS=0;\n";

		foreach($this->exportTables as $table)
		{
			$select_query = "SELECT * FROM " . $table . "";
			$statement = $connect->prepare($select_query);
			$statement->execute();
			$total_row = $statement->rowCount();
			for($count=0; $count<$total_row; $count++)
			{
				$single_result = $statement->fetch(\PDO::FETCH_ASSOC);
				$table_column_array = array_keys($single_result);

				$table_value_array = array_values($single_result);
				$output .= "\nINSERT INTO $table (";
				$output .= "" . implode(", ", $table_column_array) . ") VALUES (";
				$output .= "'" . implode("','", $table_value_array) . "');\n";
			}
		}
		//Проверяем папку
		$pathBackups = storage_path('sync/db');
		if(!is_dir($pathBackups))
		{
			mkdir($pathBackups, 0777, true);
		}
		$file_name = $pathBackups.DIRECTORY_SEPARATOR.'DB_' . date('Y-m-d H-i') . '.sql';
		$file_handle = fopen($file_name, 'w+');
		fwrite($file_handle, $output);
		fclose($file_handle);

		//Получаем список файлов
		$files = scandir(storage_path('sync/db'));
		$dates = [];
		foreach($files as $file)
		{
			if($file == '.' || $file == '..' || $file == 'last.sql')
			{
				continue;
			}
			$fileEploded = explode('_',str_replace('.sql','',$file));
			$dates[] = $fileEploded[1];
		}
		$times = array_map('strtotime', $dates);
		$latest_date = max($dates);
		$lastFile = $pathBackups.DIRECTORY_SEPARATOR.'DB_' .$latest_date. '.sql';
		$lastNameFile = $pathBackups.DIRECTORY_SEPARATOR.'last.sql';
		if(!copy($lastFile,$lastNameFile))
		{
			echo "Не удалось скопировать файл\n";
		}
		$content = '/* '.$latest_date.' */';
		$current = file_get_contents($lastNameFile);
		$handle = fopen($lastNameFile,'w+');
		fwrite($handle,$content . $current);
		fclose($handle);
	}
}
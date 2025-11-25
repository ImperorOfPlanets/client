<?php

namespace App\Http\Controllers\Control\Sync;

use App\Http\Controllers\Controller;

use DB;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

use App\Helpers\Ssh;

use App\Models\Core\Objects;
use App\Models\Core\Groups;
use App\Models\Core\Propertys;

use Illuminate\Support\Facades\File;

class SyncReInstallController extends Controller
{
	//Объект проекта
	public $object;

	public $ssh;

    public function __construct()
    {
		//Объект проекта
		$this->object = Objects::find(request()->route()->parameter(array_key_first(request()->route()->parameters())));

		$this->ssh = new Ssh(['object_id'=>$this->object->id]);

		//Получаем свойства команд
		$this->time = time();
    }

	public function index(Request $request)
	{
		View::share('project',$this->object);
		return view('control.sync.reinstall.index');
	}

	public function show(Request $request,$id)
	{
		View::share('project',$this->object);
		return view('control.sync.reinstall.'.$request->route()->parameters['reinstall']);
	}

	public function store(Request $request)
	{
		if(isset($request->command))
		{
			if($request->command=='checkConnection')
			{
				$ssh = new Ssh();
				$ssh->object = $this->object;
				try
				{
					$ssh->createSSHconnection();
				}
				catch(\Exception $e)
				{
					dd('Сбой при подключении по SSH');
				}

				//Проверяем путь до проекта
				//Linux
				$commandCDtoPAth = 'if test -d "'.$request->path_project.'"; then echo 1; fi';
				if((int)$ssh->runCommand($commandCDtoPAth)!==1)
				{return response()->json(['type'=>'alert','text' => 'Путь до проекта не находиться'],200,[],JSON_UNESCAPED_UNICODE);}
				
				//Удаляем все файлы
				$commandRM = 'rm -r '.$request->path_project.'.*';
				$ssh->runCommand($commandRM);

				//Удаляем все файлы
				$commandRM2 = 'rm -rf '.$request->path_project.'/*';
				$ssh->runCommand($commandRM2);

				//Проверяем php
				$commandPHP = $request->php_cli .' -v';
				$resultPHPExploded = explode(" ",$ssh->runCommand($commandPHP));
				if($resultPHPExploded[0]=='PHP')
				{
					if(version_compare($resultPHPExploded[1],'8.1.0')<0)
					{return response()->json(['type'=>'alert','text' => 'Версия php должна быть не ниже 8.1.0'],200,[],JSON_UNESCAPED_UNICODE);}	
				}
				else
				{
					return response()->json(['type'=>'alert','text' => 'Ошибка с PHP'],200,[],JSON_UNESCAPED_UNICODE);
				}



				//Проверяем composer
				$commandCOMPOSER = $request->php_cli.' '.$request->composer.' -V';
				$resultCOMPOSERExploded = explode(" ",$ssh->runCommand($commandCOMPOSER));
				if($resultCOMPOSERExploded[0]=='Composer')
				{
					if(version_compare($resultPHPExploded[1],'2.0.0')<0)
					{return response()->json(['type'=>'alert','text' => 'Версия composer должна быть не ниже 2.0.0'],200,[],JSON_UNESCAPED_UNICODE);}	
				}
				else
				{
					return response()->json(['type'=>'alert','text' => 'Ошибка с Composer'],200,[],JSON_UNESCAPED_UNICODE);
				}
				return response()->json(['redirect'=>route('projects.reinstall.index',['project'=>$this->object->id]).'/run'],200,[],JSON_UNESCAPED_UNICODE);
			}

			//Файл конфигурации Ngnix
			if($request->command=='checkNGNIX')
			{
				$myPath = storage_path('tmp/'.$this->object->id.'-nginx-file.conf');
				$remotePath = $this->object->propertyById(97)->pivot->value;
				$this->ssh->downloadFile($myPath,$remotePath);

				//Считываем файл построчно
				$newLines = [];
				$lines = file($myPath, FILE_IGNORE_NEW_LINES);

				$findedFastCGI = false;
				$included = [];
				$sslParams = ['ssl_certificate',/*'ssl_certificate_key',*/'ssl_ciphers','ssl_prefer_server_ciphers', 'ssl_protocols','ssl_dhparam'];
				$sslParamsAdd = [];

				foreach($lines as $kLine=>$vLine)
				{
					//Получаем последний элемент нового массива
					if(strpos($vLine,'fastcgi_pass') !== false)
					{
						$explodedFastCGI = explode(" ",$vLine);
						$lastExplodedFastCGI = $explodedFastCGI[array_key_last($explodedFastCGI)];
						$findedFastCGI = $lastExplodedFastCGI; 
					}

					foreach($sslParams as $keyParam=>$param)
					{
						//Получаем последний элемент нового массива
						if(strpos($vLine,$param) !== false)
						{
							$sslParamsAdd[] = $vLine;
						}
					}

					//Ищем подключенные конфиги
					if(strpos($vLine,'include') !== false)
					{
						$explodedInclude = explode(" ",$vLine);
						$lastExplodedInclude = $explodedInclude[array_key_last($explodedInclude)];
						
						if(strpos($lastExplodedInclude,'fastcgi_params')!==false)
						{
							//dd($lastExplodedInclude. 'strpos fastgi');
						}
						else
						{
							if($keyInclude = array_search($lastExplodedInclude,$included)===false)
							{
								//Отсеиваем 
								if('fastcgi_params')
								$included[] = $lastExplodedInclude;
							}
						}
					}
				}


				//ПРоверки
				if(!$findedFastCGI)
				{
					{return response()->json(['type'=>'alert','text' => 'Не найден fastcgi_pass (сокет)'],200,[],JSON_UNESCAPED_UNICODE);}
				}

				$domain = $this->object->propertyById(77)->pivot->value;
				$root_path =$this->object->propertyById(64)->pivot->value;
$config ='
server {
	server_name '.$domain.' www.'.$domain.';
	charset UTF-8;';
	foreach($included as $keyInclude => $includeVALUE)
	{
	$config = $config.'
	include '.$includeVALUE;
	}
	$config = $config.'
	ssi on;
	return 301 https://$host:443$request_uri;
	location / {
		try_files $uri $uri/ /index.php?$query_string;
		location ~ [^/]\.ph(p\d*|tml)$ {
			try_files /does_not_exists @php;
		}
		location ~* ^.+\.(jpg|jpeg|gif|png|svg|js|css|mp3|ogg|mpe?g|avi|zip|gz|bz2?|rar|swf)$ {
			expires 24h;
		}
	}
	location @php {
		fastcgi_index index.php;
		fastcgi_param PHP_ADMIN_VALUE "sendmail_path = /usr/sbin/sendmail -t -i -f glok87@list.ru";
		fastcgi_pass '.$findedFastCGI.'
		fastcgi_split_path_info ^((?U).+\.ph(?:p\d*|tml))(/?.+)$;
		try_files $uri =404;
		include fastcgi_params;
	}
	listen '.$this->object->propertyById(98)->pivot->value.':80;
}
server {
	server_name '.$domain.' www.'.$domain.';
';
	foreach($sslParamsAdd as $keyParam=>$param)
	{
$config = $config.$param.'
';
	}
	

$config = $config.'
	index index.php index.html;
	charset UTF-8;
	disable_symlinks if_not_owner from=$root_path;';
	foreach($included as $keyInclude => $includeVALUE)
	{
	$config = $config.'
	include '.$includeVALUE;
	}
$config = $config.'
	ssi on;
	set $root_path '.$root_path.'public;
	root $root_path;
	location / {
		try_files $uri $uri/ /index.php?$query_string;
		location ~ [^/]\.ph(p\d*|tml)$ {
			try_files /does_not_exists @php;
		}
		location ~* ^.+\.(jpg|jpeg|gif|png|svg|js|css|mp3|ogg|mpe?g|avi|zip|gz|bz2?|rar|swf)$ {
			expires 24h;
		}
	}
	location @php {
		fastcgi_index index.php;
		fastcgi_param PHP_ADMIN_VALUE "sendmail_path = /usr/sbin/sendmail -t -i -f glok87@list.ru";
		fastcgi_pass '.$findedFastCGI.'
		fastcgi_split_path_info ^((?U).+\.ph(?:p\d*|tml))(/?.+)$;
		try_files $uri =404;
		include fastcgi_params;
	}
	listen '.$this->object->propertyById(98)->pivot->value.':443 ssl;
}';
				//dd($remotePath);
				File::put(storage_path('tmp/'.$this->object->id.'-nginx-file.ready.conf'),$config);
				$this->ssh->sendFile(storage_path('tmp/'.$this->object->id.'-nginx-file.ready.conf'),$remotePath);
				return response()->json(['type'=>'alert','text' => 'NGNIX config отправлен'],200,[],JSON_UNESCAPED_UNICODE);
			}

			//Установить Laravel
			if($request->command=='installLaravel')
			{
				//Подготовка
				ini_set('default_socket_timeout',120);

				$ssh = new Ssh();
				$ssh->object = $this->object;
				try
				{
					$ssh->createSSHconnection();
				}
				catch(\Exception $e)
				{
					dd('Сбой при подключении по SSH');
				}
				try
				{
					$commandInstall = 'cd '.$this->object->propertyById(64)->pivot->value. ' && '
					.$this->object->propertyById(37)->pivot->value.' '.$this->object->propertyById(38)->pivot->value
					.' create-project laravel/laravel '.$this->object->propertyById(64)->pivot->value;
				}
				catch(\Exception $e)
				{
					{return response()->json(['type'=>'alert','text' => 'Проверьте свойства 64 37 38'],200,[],JSON_UNESCAPED_UNICODE);}
				}
				//dd($commandInstall);
				if(strpos($result = $ssh->runCommand($commandInstall),'Application key set successfully')===false)
				{
					{return response()->json(['type'=>'alert','text' => 'Неудалось установить Laravel'],200,[],JSON_UNESCAPED_UNICODE);}
				}

				//Устанавливаем Intervention/image
				$commandInstallIntervention = 'cd '.$this->object->propertyById(64)->pivot->value. ' && '
					.$this->object->propertyById(37)->pivot->value.' '.$this->object->propertyById(38)->pivot->value
					.' require intervention/image';
				$ssh->runCommand($commandInstallIntervention);
				//if(strpos($ssh->runCommand($commandInstallIntervention),'Application key set successfully')===false)
				//{
				//		{return response()->json(['type'=>'alert','text' => 'Неудалось установить Laravel'],200,[],JSON_UNESCAPED_UNICODE);}	
				//}
				return response()->json(['redirect'=>route('projects.reinstall.index',['project'=>$this->object->id]).'/run2'],200,[],JSON_UNESCAPED_UNICODE);
			}

			//Создает env файл в котором указывает БД. Указывает AUTH
			if($request->command=='checkDatabaseConnection')
			{
				$newName = uniqid('idb');
				//update the config
				config(["database.connections.".$newName=>[
					'driver'   => 'mysql',
					'database' => $request->DB_DATABASE,
					'host'     => ($request->DB_HOST ?? '127.0.0.1'),
					'port'     => ($request->DB_PORT ?? 3306),
					'username' => $request->DB_USERNAME,
					'password' => $request->DB_PASSWORD
				]]);
				//dd(config("database.connections.".$newName));
				//Check the credentials by calling PDO
				try {
					//DB::connection($newName)->getPdo();
					//return response()->json(['message'=>'Все норм'],200,[],JSON_UNESCAPED_UNICODE);
				} catch (\Exception $e) {
					dd('Переделать проверка соединения на удаленном должна быть');
					return response()->json(['message'=>'Не удалось подключиться к БД'],200,[],JSON_UNESCAPED_UNICODE);
				}

				//Записываем в проект объекта
				try
				{
					$this->object->propertys()->attach(88, ['value' => $request->DB_DATABASE]);
				}
				catch(\Exception $e)
				{
					if($e->errorInfo[1]==1062)
					{
						$this->object->propertys()->updateExistingPivot(88,['value'=>$request->DB_DATABASE]);
					}
				}
				//return response()->json(['message' => 'Значение изменено'],200,[],JSON_UNESCAPED_UNICODE);
				try
				{
					$this->object->propertys()->attach(86, ['value' => $request->DB_HOST]);
				}
				catch(\Exception $e)
				{
					if($e->errorInfo[1]==1062){$this->object->propertys()->updateExistingPivot(86,['value'=>$request->DB_HOST]);}
				}

				try
				{
					$this->object->propertys()->attach(87, ['value' => $request->DB_PORT]);
				}
				catch(\Exception $e)
				{
					if($e->errorInfo[1]==1062){$this->object->propertys()->updateExistingPivot(87,['value'=>$request->DB_PORT]);}
				}

				try
				{
					$this->object->propertys()->attach(89, ['value' => $request->DB_USERNAME]);
				}
				catch(\Exception $e)
				{
					if($e->errorInfo[1]==1062){$this->object->propertys()->updateExistingPivot(89,['value'=>$request->DB_USERNAME]);}
				}

				try
				{
					$this->object->propertys()->attach(90, ['value' => $request->DB_PASSWORD]);
				}
				catch(\Exception $e)
				{
					if($e->errorInfo[1]==1062){$this->object->propertys()->updateExistingPivot(90,['value'=>$request->DB_PASSWORD]);}
				}
				
				//Записываем конфиг
				$ssh = new Ssh();
				$ssh->object = $this->object;
				try
				{
					$ssh->createSSHconnection();
					$ssh->createSFTPconnection();
				}
				catch(\Exception $e)
				{
					dd('Сбой при подключении по SSH');
				}
				$myPath = storage_path('tmp/'.$this->object->id.'.env');
				$remotePath = $this->object->propertyById(64)->pivot->value.'.env';
				$ssh->downloadFile($myPath,$remotePath);

				//Считываем файл построчно
				$newLines = [];
				$lines = file($myPath, FILE_IGNORE_NEW_LINES);
				foreach($lines as $kLine=>$vLine)
				{
					//Получаем последний элемент нового массива
					end($newLines);
					$key = key($newLines);
					if(!is_null($key) && !empty($vLine))
					{
						//Если он не пустой
						if(!empty($newLines[$key]))
						{
							$lastExploded = explode('_',$vLine);
							$newExploded =  explode('_',$newLines[$key]);
							if($lastExploded[0]!==$newExploded[0])
							{
								$newLines[]='';
							}
						};
					}

					//Если строка пустая удаляем ее
					if(!empty($vLine))
					{
						$lastExploded = explode('_',$vLine);
						
						//Если части не совпадают добавляем строку

						if($lastExploded[0]=='APP')
						{
							$newLines[]=$vLine;
							if($lastExploded[1]=='_URL=http://localhost')
							{
								dd('Меняем localhost');
							}
						}
						elseif($lastExploded[0]=='LOG')
						{
							$newLines[]=$vLine;
						}
					}

				}

				$newLines[]='DB_CONNECTION=mysql';
				$newLines[]='DB_HOST='.($request->DB_HOST ?? '127.0.0.1');
				$newLines[]='DB_PORT='.($request->DB_PORT ?? 3306);
				$newLines[]='DB_DATABASE='.$request->DB_DATABASE;
				$newLines[]='DB_USERNAME='.$request->DB_USERNAME;
				$newLines[]='DB_PASSWORD='.$request->DB_PASSWORD;
				$newLines[]='';
				$newLines[]='QUEUE_CONNECTION=database';
				$newLines[]='';
				$newLines[]='SESSION_DRIVER=database';
				$newLines[]='SESSION_LIFETIME=120';
				$newLines[]='';
				$newLines[]='OAUTH_REDIRECT_URI='.($request->OAUTH_REDIRECT_URI ?? 'callback' );
				$newLines[]='OAUTH_SECRET='.($request->OAUTH_SECRET ?? 'none');
				$newLines[]='OAUTH_CLIENT_ID='.($request->OAUTH_CLIENT_ID?? 'none');
				$newLines[]='';
				$newLines[]='BROADCAST_DRIVER=log';
				$newLines[]='';
				$newLines[]='CACHE_DRIVER=file';
				$newLines[]='';
				$newLines[]='FILESYSTEM_DISK=local';
				$newLines[]='MEMCACHED_HOST=127.0.0.1';
/*MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"*/

				//Записываем в файл
				$str = implode("\n",$newLines);
				file_put_contents($myPath,$str);

				//Возвращаем в назад файл //надо сохранить новый env!
				// и уже копировать файлы по ssh дляр разделов и в первую очередь
				//и запускать команды npm rund build
				$ssh->sendFile($myPath,$remotePath,$chmod=0644);

				return response()->json(['redirect'=>route('projects.syncdb.index',['project'=>$this->object->id])],200,[],JSON_UNESCAPED_UNICODE);
			}

			//Установить Laravel
			if($request->command=='installNPM')
			{
				//Подготовка
				ini_set('default_socket_timeout',120);

				$ssh = new Ssh();
				$ssh->object = $this->object;
				try
				{
					$ssh->createSSHconnection();
				}
				catch(\Exception $e)
				{
					dd('Сбой при подключении по SSH');
				}

				$ssh->runCommand('export NVM_DIR="$([ -z "${XDG_CONFIG_HOME-}" ] && printf %s "${HOME}/.nvm" || printf %s "${XDG_CONFIG_HOME}/nvm")"');
				$ssh->runCommand('[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"');
				$commandInstall = 'cd '.$this->object->propertyById(64)->pivot->value. ' && npm install';

				$res = $ssh->runCommand($commandInstall);
				if(strpos($ssh->runCommand($commandInstall),'Application key set successfully')===false)
				{
					{return response()->json(['type'=>'alert','text' => 'Неудалось установить Laravel'],200,[],JSON_UNESCAPED_UNICODE);}	
				}
				return response()->json(['redirect'=>route('projects.reinstall.index',['project'=>$this->object->id]).'/run2'],200,[],JSON_UNESCAPED_UNICODE);
			}

			//Создает env файл в котором указывает БД. Указывает AUTH
			if($request->command=='checkDatabaseConnection')
			{
				$newName = uniqid('idb');
				//update the config
				config(["database.connections.".$newName=>[
					'driver'   => 'mysql',
					'database' => $request->DB_DATABASE,
					'host'     => ($request->DB_HOST ?? '127.0.0.1'),
					'port'     => ($request->DB_PORT ?? 3306),
					'username' => $request->DB_USERNAME,
					'password' => $request->DB_PASSWORD
				]]);
				//Check the credentials by calling PDO
				try {
					DB::connection($newName)->getPdo();
					//return response()->json(['message'=>'Все норм'],200,[],JSON_UNESCAPED_UNICODE);
				} catch (\Exception $e) {
					dd($e);
					return response()->json(['message'=>'Не удалось подключиться к БД'],200,[],JSON_UNESCAPED_UNICODE);
				}

				//Записываем в проект объекта
				try
				{
					$this->object->propertys()->attach(88, ['value' => $request->DB_DATABASE]);
				}
				catch(\Exception $e)
				{
					if($e->errorInfo[1]==1062)
					{
						$this->object->propertys()->updateExistingPivot(88,['value'=>$request->DB_DATABASE]);
					}
				}
				//return response()->json(['message' => 'Значение изменено'],200,[],JSON_UNESCAPED_UNICODE);
				try
				{
					$this->object->propertys()->attach(86, ['value' => $request->DB_HOST]);
				}
				catch(\Exception $e)
				{
					if($e->errorInfo[1]==1062){$this->object->propertys()->updateExistingPivot(86,['value'=>$request->DB_HOST]);}
				}

				try
				{
					$this->object->propertys()->attach(87, ['value' => $request->DB_PORT]);
				}
				catch(\Exception $e)
				{
					if($e->errorInfo[1]==1062){$this->object->propertys()->updateExistingPivot(87,['value'=>$request->DB_PORT]);}
				}

				try
				{
					$this->object->propertys()->attach(89, ['value' => $request->DB_USERNAME]);
				}
				catch(\Exception $e)
				{
					if($e->errorInfo[1]==1062){$this->object->propertys()->updateExistingPivot(89,['value'=>$request->DB_USERNAME]);}
				}

				try
				{
					$this->object->propertys()->attach(90, ['value' => $request->DB_PASSWORD]);
				}
				catch(\Exception $e)
				{
					if($e->errorInfo[1]==1062){$this->object->propertys()->updateExistingPivot(90,['value'=>$request->DB_PASSWORD]);}
				}

				//Записываем конфиг
				$ssh = new Ssh();
				$ssh->object = $this->object;
				try
				{
					$ssh->createSSHconnection();
					$ssh->createSFTPconnection();
				}
				catch(\Exception $e)
				{
					dd('Сбой при подключении по SSH');
				}
				$myPath = storage_path('tmp/'.$this->object->id.'.env');
				$remotePath = $this->object->propertyById(64)->pivot->value.'.env';
				$ssh->downloadFile($myPath,$remotePath);

				//Считываем файл построчно
				$newLines = [];
				$lines = file($myPath, FILE_IGNORE_NEW_LINES);
				foreach($lines as $kLine=>$vLine)
				{
					//Получаем последний элемент нового массива
					end($newLines);
					$key = key($newLines);
					if(!is_null($key) && !empty($vLine))
					{
						//Если он не пустой
						if(!empty($newLines[$key]))
						{
							$lastExploded = explode('_',$vLine);
							$newExploded =  explode('_',$newLines[$key]);
							if($lastExploded[0]!==$newExploded[0])
							{
								$newLines[]='';
							}
						};
					}

					//Если строка пустая удаляем ее
					if(!empty($vLine))
					{
						$lastExploded = explode('_',$vLine);
						
						//Если части не совпадают добавляем строку

						if($lastExploded[0]=='APP')
						{
							$newLines[]=$vLine;
							if($lastExploded[1]=='_URL=http://localhost')
							{
								dd('Меняем localhost');
							}
						}
						elseif($lastExploded[0]=='LOG')
						{
							$newLines[]=$vLine;
						}
					}

				}

				$newLines[]='DB_CONNECTION=mysql';
				$newLines[]='DB_HOST='.($request->DB_HOST ?? '127.0.0.1');
				$newLines[]='DB_PORT='.($request->DB_PORT ?? 3306);
				$newLines[]='DB_DATABASE='.$request->DB_DATABASE;
				$newLines[]='DB_USERNAME='.$request->DB_USERNAME;
				$newLines[]='DB_PASSWORD='.$request->DB_PASSWORD;
				$newLines[]='';
				$newLines[]='QUEUE_CONNECTION=database';
				$newLines[]='';
				$newLines[]='SESSION_DRIVER=database';
				$newLines[]='SESSION_LIFETIME=120';
				$newLines[]='';
				$newLines[]='OAUTH_REDIRECT_URI='.($request->OAUTH_REDIRECT_URI ?? 'callback' );
				$newLines[]='OAUTH_SECRET='.($request->OAUTH_SECRET ?? 'none');
				$newLines[]='OAUTH_CLIENT_ID='.($request->OAUTH_CLIENT_ID?? 'none');
				$newLines[]='';
				$newLines[]='BROADCAST_DRIVER=log';
				$newLines[]='';
				$newLines[]='CACHE_DRIVER=file';
				$newLines[]='';
				$newLines[]='FILESYSTEM_DISK=local';
				$newLines[]='MEMCACHED_HOST=127.0.0.1';
/*MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"*/

				//Записываем в файл
				$str = implode("\n",$newLines);
				file_put_contents($myPath,$str);

				//Возвращаем в назад файл
				$ssh->sendFile($myPath,$remotePath,$chmod=0644);

				return response()->json(['redirect'=>route('projects.syncdb.index',['project'=>$this->object->id])],200,[],JSON_UNESCAPED_UNICODE);
			}
		}
	}
}
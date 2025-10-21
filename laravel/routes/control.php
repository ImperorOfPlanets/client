<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

use App\Http\Controllers\RedisTestController;
use App\Http\Controllers\Control\Dns\UpdateController;

Broadcast::channel('control.projects.{project_id}.installer', function ($user, $project_id) {
    return true;
});
Route::get('/redis/test', [RedisTestController::class, 'test']);
Route::get('/redis/monitor', [RedisTestController::class, 'monitor']);
$controlPath="App\Http\Controllers\Control\\";

//Стартовая для команды
Route::get('/inteam', [$controlPath.'MainController','inteam']);

Route::post('/dns/update', [UpdateController::class, 'store']);

//Управление
Route::group(['prefix' => 'control','name'=>'control.','middleware' => 'App\Http\Middleware\Admin'],function() use ($controlPath){

	
	//Главная панель
	Route::get('/', [$controlPath.'MainController','index']);

	//Для тестов
	Route::get('test', [$controlPath.'MainController','test']);

	//Установка
	//Route::resource('install', $controlPath.'Install\\InstallController');

	//Токены
	//Route::resource('tokens', $controlPath.'Tokens\\TokensController')->withoutMiddleware([StartSession::class,]);

	//Конфигурация
	//Route::resource('configuration', $controlPath.'Configuration\\ConfigurationController');

	//Ядро
	Route::group(['prefix'=>'core','name'=>'core.'],function (){
		$corePath="App\\Http\\Controllers\\Control\\Core\\";
		Route::resource('groups.params', $corePath."ParamsController");
		Route::resource('groups', $corePath."GroupsController");
		Route::resource('objects.params', $corePath."ParamsController");
		Route::resource('objects', $corePath."ObjectsController");
		Route::resource('propertys', $corePath."PropertysController");
		Route::resource('instructions', $corePath."InstructionsController");
		Route::get('params', $corePath."ParamsController@list");
	});

	//Ядро
	Route::group(['prefix'=>'serverdashboarddesigner','name'=>'serverdashboarddesigner.','as'=>'ssd.'],function (){
		$corePath="App\\Http\\Controllers\\Control\\ServerDashboardDesigner\\";
		Route::resource('servers', $corePath."ServersController");
		Route::resource('sections', $corePath."SectionsController");
		Route::resource('blocks', $corePath."BlocksController");
		Route::resource('editor', $corePath."EditorController");
	});

	//Проекты - синхронизация файлов
	Route::resource('projects.components', $controlPath.'Projects\\ComponentsController');
	Route::resource('projects.control', $controlPath.'Projects\\ControlController');
	Route::resource('projects.reinstall', $controlPath.'Sync\\SyncReInstallController');
	Route::resource('projects.syncfiles', $controlPath.'Sync\\SyncFilesController');
	Route::resource('projects.syncdb', $controlPath.'Sync\\SyncDBController');
	Route::resource('projects.installer', $controlPath.'Installer\\InstallerController');
	Route::resource('projects.dockerconfggenerator', $controlPath.'Installer\\DockerConfGeneratorController');
	Route::resource('projects', $controlPath.'Projects\\ProjectsController');

	//Синхронизатор
	Route::resource('syncs', $controlPath.'Syncs\\SyncsController');

	//Создатель проектов
	//Route::resource('creator', $controlPath.'Creator\\CreatorController');

	//gitFlic
	Route::resource('gitflic', $controlPath.'GitFlic\\GitFlicController');

	//OpenVPN
	Route::resource('openvpn', $controlPath.'OpenVPN\\OpenVPNController');

	//Рабочее пространство
	Route::resource('workspace', $controlPath.'Workspace\\WorkspaceController');

	//Рабочее пространство
	Route::resource('reqsinteam', $controlPath.'ReqsInTeam\\ReqsInTeamController');

	//Проверятель проектов

	//Хеши файлов
	//Route::resource('hashfiles', $controlPath.'HashFiles\\HashFilesController');
});
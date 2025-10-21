<?php
//Управление Http\Controllers\Management
//Управление
Route::group(['middleware'=>'App\Http\Middleware\Management','namespace'=>'App\Http\Controllers\Management','prefix' => 'management','as'=>'m.'],function(){

	//Главная
	Route::get('/','ManagementController@index');

	//Записи
	Route::group(['namespace'=>'Wall','prefix' => 'wall','as'=>'wall.'],function(){
		Route::resource('posts',"PostsController");
		Route::resource('categories',"CategoriesController");
	});

	//Магазин
	Route::group(['namespace'=>'Shop','prefix' => 'shop','as'=>'shop.'],function(){
		Route::resource('products',"ProductsController");
		Route::resource('baskets',"BasketsController");
		Route::resource('categories',"CategoriesController");
		Route::resource('orders',"OrdersController");
	});

	//Платежи
	Route::group(['namespace'=>'Payments','prefix' => 'payments','as'=>'payments.'],function(){
		Route::resource('/currencys','CurrencysController');
		Route::resource('/provaiders','ProvaidersController');
		Route::resource('/payments','PaymentsController');
		Route::resource('/statuses','StatusesController');
	});

	//Ассистент
	Route::group(['namespace'=>'Assistant','prefix' => 'assistant','as'=>'assistant.'],function(){
		Route::resource('commands',"CommandsController");
		Route::resource('messages',"MessagesController");
		Route::resource('settings',"SettingsController");
		//Фильтры
        Route::resource('filters',"FiltersController");
        Route::get('filters/{id}/parameters', ["\App\Http\Controllers\Management\Assistant\FiltersController", 'getParameters'])->name('filters.parameters');
		
        Route::resource('learning',"LearningController");
        Route::resource('browser',"BrowserController");

        // Дополнительные маршруты для браузера
        // Стало (если id не обязателен):
        Route::get('browser/{id?}/debug', [App\Http\Controllers\Management\Assistant\BrowserController::class, 'debug'])->name('browser.debug')->where('id', '.*'); // разрешаем любые значения, включая пустые
        Route::get('browser/health/status', [App\Http\Controllers\Management\Assistant\BrowserController::class, 'health'])->name('browser.health');
        Route::post('browser/partial/results', [App\Http\Controllers\Management\Assistant\BrowserController::class, 'partialResults'])->name('browser.partial.results');
	});

	//Пользователи
	Route::group(['namespace'=>'Users','prefix' => 'users','users'=>'users.'],function(){
		Route::resource('users',"UsersController");
		Route::resource('roles',"RolesController");
	});

	//Организации
	Route::group(['prefix' => 'organizations'],function(){

	});

	//Парсеры
	/*Route::group(['namespace'=>'Parser','prefix' => 'parser','as'=>'parser.'],function(){
		Route::resource('parsers',"ParserController");
		Route::resource('updates',"UpdatesController");
		Route::resource('iframe',"IframeController");
	});*/

	//Настройки
	Route::group(['namespace'=>'Settings','prefix' => 'settings','as'=>'settings.'],function(){
		Route::resource('basic',"SettingsController");
		Route::resource('site',"Site\SiteController");
		Route::resource('processes',"Processes\ProcessesController");
		Route::resource('keywords',"Keywords\KeywordsController");
		Route::resource('files',"Files\FilesController");
		Route::resource('logs',"Logs\LogsController");
		Route::resource('queues',"Queues\QueuesController");
		Route::resource('ips',"Ips\IpsController");
		Route::resource('parsers',"Parser\ParserController");
		Route::resource('sockets',"Sockets\SocketsController");
		Route::resource('pwa',"Pwa\PwaController");
	});

    Route::group(['namespace'=>'Ai','prefix' => 'ai', 'as' => 'ai.'], function () {
        Route::resource('requests', "AiRequestsController");
        Route::resource('services', "AiServicesController");
        Route::resource('promts', "AiPromtsController");

    });
	//Route::resource('organizations',"Organizations\\OrganizationsController");
	//Route::resource('pages',"Pages\\PagesController");
	//Route::resource('docs',"Docs\\DocsController");
    //Route::resource('settings',"Settings\\SettingsController");

	//Route::resource('files',"Files\\FilesController");
});

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

Route::get('/fix-fields-data', function () {
    $tables = collect(DB::select('SHOW TABLES'))
        ->map(function ($item) { return head((array)$item); })
        ->filter(function ($tableName) { return Str::endsWith($tableName, '_fields'); });

    $report = [];

    foreach ($tables as $tableName) {
        $rows = DB::table($tableName)->get();

        foreach ($rows as $row) {
            $params = $row->params;
            $id = $row->id;
            $originalJson = $params;

            // Пропускаем пустые значения
            if (empty($params)) {
                $report[] = [
                    'table' => $tableName,
                    'id' => $id,
                    'error' => 'Empty params',
                    'old_value' => $originalJson,
                    'new_value' => null
                ];
                continue;
            }

            // Пытаемся декодировать JSON
            $data = json_decode($params, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $report[] = [
                    'table' => $tableName,
                    'id' => $id,
                    'error' => 'Invalid JSON: ' . json_last_error_msg(),
                    'old_value' => $originalJson,
                    'new_value' => null
                ];
                continue;
            }

            // Проверяем структуру access
            $hasIssues = false;
            $newData = $data; // Копируем данные для модификации

            // Case 1: access отсутствует или не массив
            if (!isset($newData['access']) || !is_array($newData['access'])) {
                $newData['access'] = [];
                $hasIssues = true;
            }

            // Case 2: Проверяем show/edit
            foreach (['show', 'edit'] as $key) {
                if (!isset($newData['access'][$key]) || !is_array($newData['access'][$key])) {
                    $newData['access'][$key] = [];
                    $hasIssues = true;
                } else {
                    // Проверяем типы значений
                    foreach ($newData['access'][$key] as $k => $v) {
                        $newVal = filter_var($v, FILTER_VALIDATE_BOOLEAN);
                        if ($newVal !== $v) {
                            $newData['access'][$key][$k] = $newVal;
                            $hasIssues = true;
                        }
                    }
                }
            }

            // Если есть изменения - добавляем в отчет
            if ($hasIssues) {
                $fixedJson = json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                $report[] = [
                    'table' => $tableName,
                    'id' => $id,
                    'error' => 'Structure issue',
                    'old_value' => $originalJson,
                    'new_value' => $fixedJson
                ];
            }
        }
    }

    // Возвращаем отчет в JSON формате
    return response()->json([
        'count' => count($report),
        'issues' => $report
    ]);
});
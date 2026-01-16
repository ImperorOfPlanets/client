<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

			//Авторизация MyIdOn.SITE
Route::get('logout',function(){session()->forget('user_id');return redirect('/');})->name('logout');

Route::get('auth/callback', function (Request $request) {
    $state = $request->session()->pull('state');
    $redirectUri = $request->session()->pull('oauth_redirect_uri');
    $clientId = $request->session()->pull('oauth_client_id');
    $clientSecret = $request->session()->pull('oauth_client_secret');

    throw_unless(
        !empty($state) && hash_equals($state, (string) $request->state),
        new InvalidArgumentException('Invalid state parameter.')
    );

    $tokenResponse = Http::asForm()->post('https://myidon.site/oauth/token', [
        'grant_type'    => 'authorization_code',
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri'  => $redirectUri,
        'code'          => $request->code,
    ]);

    $data = $tokenResponse->json();

    if (!isset($data['access_token'])) {
        \Log::error('OAuth token error', $data);
        return redirect('/')->withErrors(['auth' => 'Failed to authenticate.']);
    }

    // Получаем данные пользователя
    $userResponse = Http::withToken($data['access_token'])
        ->get('https://myidon.site/api/user', ['uid' => 1]);

    if ($userResponse->successful()) {
        $user = $userResponse->json();
        if (isset($user['id'])) {
            $request->session()->put('user_id', $user['id']);
        }
    }

    return redirect('/');
});

			//Стена
Route::group(['namespace'=>'App\Http\Controllers\Wall'],function($e){
	Route::resource('/wall','PostsController');
});

			//Магазин

Route::group(['namespace'=>'App\Http\Controllers\Shop'],function($e){
	Route::resource('shop/tree',"TreeController");
	Route::resource('shop/basket',"BasketController");
	Route::resource('shop',"ShopController");
});



			//Страницы
Route::get('pages/{id}',[App\Http\Controllers\Pages\PagesController::class,'show'])->name('pages');



			//Файлы
	//Загрузка
	Route::post('files', [App\Http\Controllers\Files\FilesController::class, 'fileUpload'])->name('fileUpload');
	//Просмотр
	Route::get('files/{guid}', [App\Http\Controllers\Files\FilesController::class, 'fileDownload'])->name('fileDownload');



			//Помощник
//Route::resource('/commands',[AssistantController::class,'commands']);
Route::resource('assistant',"App\Http\Controllers\Assistant\AssistantController");


//Route::group(['namespace'=>'App\Http\Controllers\Assistant','prefix' => 'assistant'],function(){
	//Route::resource('/commands',[AssistantController::class,'commands']);
//	Route::resource('/',"AssistantController");
//});


			//Баланс
//Route::resource('balance', BalanceController::class);


//Страницы оплаты
Route::group(['prefix' => 'payments'],function(){
	Route::view('/success','payments.success');
	Route::view('/fail','payments.fail');
	Route::view('/inprogress','payments.inprogress');
	Route::view('/return','payments.return');
	Route::view('/iframe','payments.iframe');
});



			//Обратная связь
//Route::resource('feedback', FeedbackController::class);



			//Пользователь
Route::group(['namespace'=>'App\Http\Controllers\User','prefix' => 'user'],function(){
	Route::resource('settings',"SettingsController")->middleware('auth');
});


			//Платежи
Route::group(['namespace'=>'App\Http\Controllers\Payments'],function(){
	Route::resource('payments/provaiders',"ProvaidersController");
	Route::resource('payments/currencys',"CurrencysController");
	Route::resource('payments',"PaymentsController");
});

//Route::group(['namespace'=>'App\Http\Controllers\Payments','prefix' => 'payments'],function($e){
//	Route::resource('/provaiders',"ProvaidersController");
//	Route::resource('/currencys',"CurrencysController");
//});
//Route::resource('/',"PaymentsController");




//Языковая поддержка
Route::get('/locale/{id}',function($id){
	if(in_array($id,['en', 'ru', 'cn']))
	{
		session()->put('locale',$id);
		App::setLocale($id);
	}
	else
	{
		dd('not nocale');
	}
	return redirect(url(URL::previous()));
});

			//Шлюз взаимодейтвий
Route::resource('gateway',App\Http\Controllers\Gateway\GatewayController::class);//->middleware(App\Http\Middleware\GatewayIPAddress::class);

//CSRF
Route::get('/get-csrf-token',function (){
	return csrf_token();
});

			//Инструкции
Route::get('/instructions/{template}', function ($template) {
	// Проверяем, существует ли представление с указанным именем
	if (view()->exists("instructions.$template")) {
		return view("instructions.$template");
	}

	// Если представление не найдено, возвращаем ошибку 404
	abort(404, 'Инструкция не найдена');
});

//Языковая поддержка
Route::get('/locale/{id}', function ($id) {
	if(in_array($id,['en', 'ru', 'cn']))
	{
		session()->put('locale',$id);
		App::setLocale($id);
	}
	else
	{
		dd('not nocale');
	}
	return redirect(url(URL::previous()));
});

Route::get('/test-embedding', function() {
    $service = new \App\Helpers\Ai\Services\LocalEmbeddingService([
        'tei_url' => env('EMBEDDING_SERVICE_URL', 'http://localhost:5000'),
        'model' => env('EMBEDDING_MODEL', 'BAAI/bge-m3')
    ]);
    
    $result = $service->getEmbedding([
        'text' => 'Тестирование работы сервиса векторизации'
    ]);
    
    return response()->json([
        'success' => $result['success'],
        'dimensions' => count($result['embedding']),
        'sample_values' => array_slice($result['embedding'], 0, 5)
    ]);
});
<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Shop\ProductsModel;
use App\Models\Shop\BasketModel;

class BasketController extends Controller
{

	public function index()
	{
		$basket = $this->getBasket();
		$products = [];
		foreach($basket as $prod)
		{
			$product = ProductsModel::find($prod['id']);
			$product->count = $prod['count'];
			$products[] = $product;
		};
		return view('shop.basket',[
			'basket'=>$products
		]);
	}

	//Получение корзины
	public function getBasket()
	{
		$user = session()->get('user_id');
		//Получение
		if(is_null($user))
		{
			//Проверяем корзину в сессии
			$basket = session()->get('basket');

			if(is_null($basket) or $basket=='null')
			{
				$basket = [];
			}
			else
			{
				$basket = json_decode($basket,true);
			}
		}
		else
		{
			//Проверяем наличие корзины
			$basketInDB = BasketModel::whereHas('propertys',function($query) use ($user){
				$query->where('property_id','=',83)->where('value','=',$user);
			})->get();
			if(count($basketInDB)==0)
			{
				$basket = [];
			}
			elseif(count($basketInDB)==1)
			{
				$basket = json_decode($basketInDB[0]->propertyById(10)->pivot->value,true);
			}
			else
			{
				dd('error');
			}
		}
		return $basket;
	}

	//Установка корзины нового значения
	public function setBasket($basket)
	{
		$user = session()->get('user_id');
		if(is_null($user))
		{
			session(['basket' => json_encode($basket)]);
		}
		else
		{
			$basketInDB = BasketModel::whereHas('propertys',function($query) use ($user){
				$query->where('property_id','=',83)->where('value','=',$user);
			})->get();

			if(count($basketInDB)==0)
			{
				//Создаем корзину
				$newBasket = new BasketModel;
				$newBasket->save();

				$newBasket->propertys()->attach(83,['value'=>$user]);
				$newBasket->propertys()->attach(10,['value'=>json_encode($basket)]);
			}
			elseif(count($basketInDB)==1)
			{
				$property = $basketInDB[0]->propertyById(10);
				$property->pivot->value=json_encode($basket);
				$property->pivot->save();
			}
			else
			{
				dd('error');
			}
		}
	}

	//Установка корзины нового значения
	public function addInBasket($basket,$product)
	{
		//Проверка в корзине
		if(count($basket) == 0)
		{
			$basket[] = $product;
		}
		else
		{
			$finded = false;
			foreach($basket as $productInBasket)
			{
				//Ecли совпадают, то надо проверить свойства! если совпадают надо добавить количество
				if($product['id']==$productInBasket['id'])
				{
					$finded = true;
				}
			}

			//Если товар найден проверка по атрибутам
			if($finded)
			{
				dd('Найден');
			}
			else
			{
				$basket[] = $product;
			}
		}
		return $basket;
	}

	public function store(Request $request)
	{
		//Добавляем продукт в корзину
		$product = [
			'id'=>$request->product_id,
			'count'=>$request->count
		];

		//Получение корзины
		$basket = $this->getBasket();

		//Добавление в корзину
		$basket = $this->addInBasket($basket,$product);

		//Сохранение
		$this->setBasket($basket);

		//$newBasket->propertys()->attach($request->property_id,['value'=>$request->value]);

		//dd(session()->all());
		//dd('Проверяем авторизацию! Если авторизирован дергаем корзину! Если нет записываем в сессию');
	}
}
<?php

namespace App\Http\Controllers\Management\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Shop\ProductsModel;

class ProductsController extends Controller
{
	public function index()
	{
		$products = ProductsModel::paginate(15);
		return view('management.shop.products.index',['products'=>$products]);
	}

	public function create()
	{
		return view('management.shop.products.create');
	}

	public function store(Request $request)
	{
		$product = new ProductsModel;
		$product->save();
		return redirect()->route('m.shop.products.edit', $product->id);
	}

	public function edit($id)
	{ 
		$product = ProductsModel::find($id);
		return view('management.shop.products.edit',[
			'product'=>$product
		]);
	}

	public function update(Request $request,$id)
	{
		$product = ProductsModel::find($id);

		//Проверяем название в запросе
		if(!is_null($request->name))
		{
			//Получаем значение из БД
			$name = $product->propertyById(1);
			//Если значения нет
			if(is_null($name))
			{
				$product->propertys()->attach(1,['value'=>$request->name]);
			}
			else
			{
				$name->pivot->value=$request->name;
				$name->pivot->save();
			}
		}
		elseif(isset($request->command) && $request->command=='change-property')
		{
			$property = $product->propertyById($request->property_id);
			if(is_null($property))
			{
				$product->propertys()->attach($request->property_id,['value'=>$request->value]);
			}
			else
			{
				$property->pivot->value=$request->value;
				$property->pivot->save();
			}
		}
		return redirect('/management/products/'.$product->id.'/edit');
	}
}
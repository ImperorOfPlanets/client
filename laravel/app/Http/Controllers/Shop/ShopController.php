<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\BasketController;

use Illuminate\Http\Request;

use App\Models\Shop\ProductsModel;
use App\Models\Shop\CategoriesModel;

class ShopController extends Controller
{
	public function index()
	{
		$products = ProductsModel::paginate(15);
		$basketController = new BasketController;
		$basket = $basketController->getBasket();
		return view('shop.index',[
			'products'=>$products,
			'basket'=>$basket
		]);
	}

	public function show(Request $request,$id)
	{
		if($id=='tree')
		{
			$categories = CategoriesModel::all();
			$data = array();
			foreach($categories as $category)
			{
				$tmp = array();
				$tmp['id'] = $category->id;
				$tmp['name'] = $category->propertyById(1)->pivot->value;
				$tmp['parent_id'] = $category->propertyById(112)->pivot->value;;
				array_push($data, $tmp); 
			}

			$itemsByReference = array();

			foreach($data as $key => &$item)
			{
				$itemsByReference[$item['id']] = &$item;
				// Children array:
				$itemsByReference[$item['id']]['nodes'] = array();
			}

			// Set items as children of the relevant parent item.
			foreach($data as $key => &$item) 
			{
				//echo "<pre>";print_r($itemsByReference[$item['parent_id']]);die;
				if($item['parent_id'] && isset($itemsByReference[$item['parent_id']]))
				{
					$itemsByReference [$item['parent_id']]['nodes'][] = &$item;
				}
			}

			// Remove items that were added to parents elsewhere:
			foreach($data as $key => &$item)
			{
				if(empty($item['nodes']))
				{
					unset($item['nodes']);
				}
				if($item['parent_id'] && isset($itemsByReference[$item['parent_id']]))
				{
					unset($data[$key]);
				}
			}

			echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		}
		elseif($id=='basket'){
			$basketController = new BasketController;
			$basket = $basketController->getBasket();
		}
	}
}
<?php

namespace App\Http\Controllers\Management\Shop;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\Shop\CategoriesModel;

class CategoriesController extends Controller
{
	public function index()
	{
		$categories = CategoriesModel::paginate(15);
		return view('management.shop.categories.index',[
			'categories'=>$categories
		]);
	}

	public function store(Request $request)
	{
		$category = new categoriesModel;
		$category->save();
		return redirect()->route('categories.edit', $category->id);
	}

	public function create(Request $request)
	{
		$categories = CategoriesModel::all();
		return view('management.shop.categories.create',[
			'categories'=>$categories
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
				$tmp['parent_id'] = $category->propertyById(112)->pivot->value ?? 0;
				$tmp['nodes'] = [];
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

			echo json_encode($data,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
	}

	public function edit(Request $request,$id)
	{
		$category = CategoriesModel::find($id);
		return view('management.shop.categories.edit',[
			'category'=>$category
		]);
	}

	public function update(Request $request,$id)
	{
		$category = CategoriesModel::find($id);
		if($request->command == 'saveProperty')
		{
			$property = $category->propertyById($request->property_id);
			if(is_null($property))
			{
				$category->propertys()->attach($request->property_id,['value'=>$request->value]);
			}
			else
			{
				$property->pivot->value=$request->value;
				$property->pivot->save();
				//$doc->propertys()->attach($request->property_id,['value'=>$request->value]);
			}
		}
		elseif(isset($request->command) && $request->command=='change-property')
		{
			$property = $category->propertyById($request->property_id);
			if(is_null($property))
			{
				$category->propertys()->attach($request->property_id,['value'=>$request->value]);
			}
			else
			{
				$property->pivot->value=$request->value;
				$property->pivot->save();
			}
		}
	}
}
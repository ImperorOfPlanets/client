<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\Shop\CategoriesModel;

class TreeController extends Controller
{
	public function index()
	{
		$categories = CategoriesModel::all();
		$data = [];
		foreach($categories as $category)
		{
			$tmp = array();
			$tmp['id'] = $category->id;
			$tmp['text'] = $category->propertyById(1)->pivot->value;
			$tmp['parentId'] = $category->propertyById(112)->pivot->value ?? 0;
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
			if($item['parentId'] && isset($itemsByReference[$item['parentId']]))
			{
				$itemsByReference [$item['parentId']]['nodes'][] = &$item;
			}
		}

		// Remove items that were added to parents elsewhere:
		foreach($data as $key => &$item)
		{
			if(empty($item['nodes']))
			{
				unset($item['nodes']);
			}
			if($item['parentId'] && isset($itemsByReference[$item['parentId']]))
			{
				unset($data[$key]);
			}
		}
		$data = array_values($data);
		echo str_replace(array("\r\n", "\n", "\r"),'',json_encode($data,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}
}
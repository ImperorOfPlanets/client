<?php
namespace App\Http\Controllers\Control\Workspace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Core\Objects;
use App\Models\Core\Groups;

class WorkspaceController extends Controller
{

	public function index()
	{
		return view('control.workspace.index');
	}

	public function show(Request $request,$id)
	{
		if($id == 'projects')
		{
			$resultJSON = [];

			//Получаем группу проектов
			$group = Groups::find(9);

			//Получаем список проектов
			$projects = $group->objects;
			foreach($projects as $project)
			{
				//Получаем свойство isOn для проекта удостверяем что он включен
				$isOn = $project->propertyById(116)->pivot->value ?? null;
				//Если не ноль, значит проверяем на bollean
				if(!is_null($isOn))
				{
					if(filter_var($isOn,FILTER_VALIDATE_BOOLEAN))
					{
						$resultJSON[$project->id]=[];
					}
				}
			}
			return response()->json($resultJSON);
		}
		elseif($id=='pingmyidon')
		{
			$host="192.168.0.104";

			exec("ping -c 4 " . $host, $output, $result);
			
			print_r($output);
			
			if ($result == 0)
			
			echo "Ping successful!";
			
			else
			
			echo "Ping unsuccessful!";
		}
	}
}
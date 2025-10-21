<?php

namespace App\Http\Controllers\Wall;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Files\FilesModel;
use App\Models\Wall\PostModel;

class PostsController extends Controller
{
	public function index()
	{
		//Получаем первую десятку
		$posts = PostModel::limit(10)->get();
		return view('wall.posts.index',['posts'=>$posts]);
	}

	public function create()
	{
	}

	public function store(Request $request)
	{
		if(!Auth::user()){return redirect('/login');}
		if(!isset($request->command)){return redirect('/login');}

		//Сохраняем текст	
		if($request->command=='saveText')
		{
			$request->session()->put('posts.create.text',$request->text);
		}

		//Отправить на модерацию	
		if($request->command=='post-send')
		{
			$text = strip_tags($request->session()->get('posts.create.text'));
			$images = $request->session()->get('posts.create.images');

			//Проводим проверку текста
			$text = $request->session()->get('posts.create.text');
			if(strlen($text)<30 && count($images)==0)
			{return response()->json(['type' => 'alert','text' => 'Текст слишком короткий']);}
			elseif(strlen($text)>2000)
			{return response()->json(['type' => 'alert','text' => 'Текст слишком длинный']);}

			//Проверяем изображения
			if(count($images)>8)
			{return response()->json(['type' => 'alert','text' => 'Не более 8 изображений']);}

			//Сохраняем в БД
			try
			{
				//Сам пост
				$post = new Objects;
				$post->save();
				//Добавляем в группу
				$post->groups()->attach($this->groupID);

				//Статус
				$post->propertys()->attach(13,['value'=>0]);
				//Автор
				$post->propertys()->attach(12,['value'=>Auth::id()]);
				//Текст
				$post->propertys()->attach(10,['value'=>$text]);
				
				//Изображения
				for($i=0;$i<count($images);$i++)
				{
					$file = Files::find(str_replace("-","",$images[$i]));
					$newInfo['forPost'] = $post->id;
					$file->add_info = $newInfo;
					$file->save();
				}
				$post->propertys()->attach(11,['value'=>json_encode($images)]);
			}
			catch(\Exception $e){'Сбой при записи в БД записи';}

			//Добавляем задачу модераторам
			try
			{
				//Сам пост
				$task = new Objects;
				$task->save();
				$taSK->groups()->attach(6);
				$task->propertys()->attach(10,['value'=>'Провести проверку поста на модерацию']);
			}
			catch(\Exception $e){'Сбой при создании задачи модератору';}
			$request->session()->forget('posts.create');
			return response()->json(['type' => 'alert','text' => 'Запись отправленна на модерацию']);
		}
	}

	public function show($id)
	{
	}

	public function edit($id)
	{
	}

	public function update(Request $request, $id)
	{
	}

	public function destroy($id)
	{
	}
}
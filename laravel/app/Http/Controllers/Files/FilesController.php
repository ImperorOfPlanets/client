<?php
namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Files\FilesModel;

use Intervention\Image\ImageManagerStatic as Image;
use Ramsey\Uuid\Uuid;

class FilesController extends Controller
{
	//GUID  файла
	public $GUID = false;

	//Дополнительная информация
	public $addInfo = [];

	//Доступные заголовки
	public $acceptHeaders = ['postCreate'];

	//Заголовки ответа
	public $responseHeaders = [];

	//Максимальная ширина и высота загружанных файлов
	public $maxWidth = 1920;
	public $maxHeight = 1080;

	//Максимальная ширина и высота загружанных файлов
	public $maxPreviewWidth = 200;
	public $maxPreviewHeight = 200;

	//Выполняет базовые вычисления
	public function basicActionGUID()
	{
		$this->GUID->DIRECTORY = new \StdClass;
		$this->GUID->DIRECTORY->PARTS = new \StdClass;
		$this->GUID->PARTS = explode('-',$this->GUID->toString());
		$this->GUID->LETTERS = implode('',$this->GUID->PARTS);
		sscanf($this->GUID->LETTERS,"%3s%3s%3s",$this->GUID->DIRECTORY->PARTS->ONE,$this->GUID->DIRECTORY->PARTS->TWO,$this->GUID->DIRECTORY->PARTS->THREE);
		$this->GUID->DIRECTORY->PATH = 'files/'.$this->GUID->DIRECTORY->PARTS->ONE.'/'.$this->GUID->DIRECTORY->PARTS->TWO.'/'.$this->GUID->DIRECTORY->PARTS->THREE;
		$this->GUID->PATH = $this->GUID->DIRECTORY->PATH.'/'.$this->GUID->toString();
		$this->GUID->FULLPATH = storage_path('app/'.$this->GUID->PATH);
	}

	//Проверка файла и возвращение всех данных
	public function getFileInfo()
	{
		//Получаем тип
		$this->GUID->mimeType = new \StdClass;
		$this->GUID->mimeType->TEXT = Storage::mimeType($this->GUID->PATH);
		$this->GUID->mimeType->PARTS = explode('/',$this->GUID->mimeType->TEXT);

		//Проверяем изображение это или нет
		if($this->GUID->mimeType->PARTS[0]=='image'){
			$this->GUID->thisImage = true;
		}else{
			$this->GUID->thisImage = false;
		}

		//Получаем размеры
		if($this->GUID->thisImage)
		{
			$data = getimagesize($this->GUID->FULLPATH);
			$this->GUID->width = $data[0];
			$this->GUID->height = $data[1];
		}
	}

	//Проверка заголовков
	public function checkHeaders()
	{
		//Проверяем заголовок x-upload
		if(request()->header('x-upload',null)===null)
		{return response()->json(['alert' =>'Отсутствует заголовок upload'],200,[],JSON_UNESCAPED_UNICODE);}

		if(!in_array(request()->header('x-upload'),$this->acceptHeaders))
		{return response()->json(['alert' =>'Недопустимый заголовок upload'],200,[],JSON_UNESCAPED_UNICODE);}
		$this->addInfo['x-upload']=request()->header('x-upload');

		//Проверяем заголовок x-fid
		if(request()->header('x-fid',null)===null)
		{return response()->json(['alert' =>'Отсутствует заголовок fid'],200,[],JSON_UNESCAPED_UNICODE);}
		$this->responseHeaders['x-fid']=request()->header('x-fid');
	}

	//Выполняет действия перед загрузкой файла
	public function beforeUpload()
	{
		//Создание поста
		if(request()->header('x-upload')==='postCreate')
		{
			//Проверяем количество избражений
			if(session()->get('posts.create.images')!==null)
			{};
		}
	}

	//Выполняет действия после загрузки файла
	public function afterUpload()
	{
		//Создание поста
		if(request()->header('x-upload')==='postCreate')
		{
			//Добавляем файл в сессию
			session()->push('posts.create.images',$this->GUID->toString());
		}
	}

	//Загрузка файла
	public function fileUpload(Request $request)
	{
		//Проверка заголовков
		$this->checkHeaders();

		//Выплняет действия перед загрузкой
		$this->beforeUpload();

		//Загружаем файлы
		if($request->hasFile('file')){
			//Генерируем guid
			$fullName = $_FILES['file']['name'];
			$filename = pathinfo($fullName, PATHINFO_FILENAME);
			$ext = pathinfo($fullName, PATHINFO_EXTENSION);
			$this->GUID = Str::uuid();
			$this->basicActionGUID();
			//Копирование файла в хранилище
			try{
				Storage::makeDirectory($this->GUID->DIRECTORY->PATH); 		
				$request->file('file')->storeAs($this->GUID->DIRECTORY->PATH,$this->GUID->toString());
			}catch(\Exception $e){
				return response()->json(['error' =>'Произошел сбой копировании файла'],200,$this->responseHeaders,JSON_UNESCAPED_UNICODE);
			}

			//Получаем данные файлы
			$this->getFileInfo();

			//Если изображение меняем размер (по надобности)
			if($this->GUID->thisImage){$this->imageCheckForResize();}

			//Запись файла в БД
			try{
				
				$fileModel = new FilesModel;
				$fileModel->save();

				$fileModel->guid=$this->GUID->LETTERS;
				$fileModel->filename=$filename;
				$fileModel->ext=$ext;
				$fileModel->user_id=session()->get('user_id',null);
				$fileModel->access=0;
				$fileModel->ip=$request->ip();
				$fileModel->add_info = $this->addInfo;
				$fileModel->save();
			}catch(\Exception $e){
				dd($e);
				$this->deleteFileOnHard($this->GUID->DIRECTORY,$this->GUID->toString());
				return response()->json(['error' =>$e],200,$this->responseHeaders,JSON_UNESCAPED_UNICODE);
			}
		}else{
			return response()->json(['error' =>'Ошибка (возможно слишком большой размер файла)'],200,$this->responseHeaders,JSON_UNESCAPED_UNICODE);
		}

		//Дополнительные действия для заголовков
		$this->afterUpload();

		//Отправляем ответ
		return response()->json(array(
			'guid'=>$this->GUID->toString(),
			'result'=>'ok'
		),200,$this->responseHeaders,JSON_UNESCAPED_UNICODE);
	}

	//Удаление на сервере
	public function deleteFileOnHard($DIRECTORY,$FILENAME){
		Storage::delete($DIRECTORY->PATH.'/'.$FILENAME);
	}

	//Доступ к изображениям с размерами
	public function fileDownload(Request $request,$guid)
	{	
		//Проверяем guid
		if(is_null($guid)){return null;}
		//Формируем основные данные
		$this->GUID = Uuid::fromString($guid);
		$this->basicActionGUID();
		$this->getFileInfo();

		//Проверяем наличие файла
		if(!file_exists($this->GUID->FULLPATH))
		{echo "Файл остуствует";}

		//Проверяем ширину
		if($request->has('w'))
		{
			//Проверяем что изображение
			if(!$this->GUID->thisImage)
			{echo 'fail';}

			//Запрашиваемая ширина до 200px
			if($request->w<=200)
			{
				//Если файл отсуствует то создаем новый
				if(!file_exists($this->GUID->FULLPATH.'_200'))
				{
					//Проверяем что оригинал больше
					$img = Image::make($this->GUID->FULLPATH);
					$img->resize($this->maxPreviewWidth,null,function ($constraint){$constraint->aspectRatio();})->save($this->GUID->FULLPATH.'_200');
				}
				return response()->file($this->GUID->FULLPATH.'_200');
			}
		}

		return response()->file($this->GUID->FULLPATH);
	}

	//Удалить файл
	public function deleteFile($guid)
	{
		
	}

	//Проверяем если изображение то делаем resize
	public function imageCheckForResize()
	{
		//Параметр по которому производится уменьшение
		$paramForResize = null;

		//Проверяем что больше ширина или высота
		if($this->GUID->width > $this->GUID->height)
		{$paramForResize='width';}
		elseif($this->GUID->width < $this->GUID->height)
		{$paramForResize='height';}
		else{{$paramForResize='width';}}

		//Производим подгонку по параметру
		if($paramForResize=='width')
		{
			//Если ширина больше допустимой
			if($this->GUID->width > $this->maxWidth)
			{
				$img = Image::make($this->GUID->FULLPATH);
				$img->resize($this->maxWidth,null,function ($constraint){$constraint->aspectRatio();})->save($this->GUID->FULLPATH);
			}
		}
		else
		{
			//Если высота больше допустимой
			if($this->GUID->height > $this->maxHeight)
			{
				$img = Image::make($this->GUID->FULLPATH);
				$img->resize(null,$this->maxHeight,function ($constraint){$constraint->aspectRatio();})->save($this->GUID->FULLPATH);
			}
		}
	}
}
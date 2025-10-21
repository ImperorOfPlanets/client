<?php

namespace App\Http\Controllers\Management\Settings;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Helpers\Files\Files;

use Illuminate\Http\Filesystem\TempFile;

class SettingsController extends Controller
{
	public function index()
	{
		return view('management.settings.index');
	}

	public function store(Request $request)
	{
		if($request->command = 'updatePhoto')
		{
			$request->validate([
				'file' => 'required|image|mimes:png',
			]);

			$file = $request->file('file');
			$path = $file->storeAs('tmp','logo.png');
			$pathBasicUploaded =storage_path('app'.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'logo.png');
			//dd($pathBasicUploaded);
			//$width = Image::make($file)->width();
			$img = imagecreatefrompng($pathBasicUploaded);
			$width = imagesx($img);
			$heigth = imagesy($img);
			if($width<999 || $heigth<999 || $heigth!=$width)
			{
				dd("Разрешение не маленькое или не 1 к 1");
			}

			$newWidth = 1000;
			$newHeight = 1000;

			$newImage = imagecreatetruecolor($newWidth, $newHeight);
			$transparent = imagecolorallocate($newImage, 255, 255, 255);
			//imagecolortransparent($newImage, $transparent);
			//imagefill($newImage, 0, 0, $transparent);
imagealphablending($newImage , false);
imagesavealpha($newImage , true);
imagecopyresampled($newImage,$img,0,0,0,0,$newWidth,$newHeight,$width,$heigth);
		
//imagecopyresampled ( $newImage, $image, 0, 0, 0, 0, $width, $height, imagesx ( $image ), imagesy ( $image ) );

//$image = $new_image;

// saving
imagealphablending($img,false);
imagesavealpha($img , true);
//imagepng ( $image, $filename );
			$pbPath = public_path('logo.png');
			// Сохраняем новое изображение
			imagepng($newImage,$pbPath);

// Освобождаем память
imagedestroy($img);
imagedestroy($newImage);

			//$destinationPath = public_path().'/img' ;
			//$file->move($destinationPath,'logo-header.png');
		}
	}
}
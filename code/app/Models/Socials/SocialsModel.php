<?php

namespace App\Models\Socials;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Propertys;

use Illuminate\Support\Facades\DB;

class SocialsModel extends Model
{
	protected $table = "socials_objects";

	protected $primaryKey = "id";

	protected $casts = [
		"params" => "array",
	];

	protected $attributes = [
        'params' => '{
            "name": null,               // Основной текст промта
            "desk": null,           	// Список переменных шаблона
			"require": 0,				// Обязательность
			"access": {}				// Доступ
        }'
    ];

	protected function asJson($value,$flag=0)
	{
		return json_encode($value,JSON_UNESCAPED_UNICODE);
	}
	public function propertys()
	{
		return $this->belongsToMany(Propertys::class,"socials_propertys","object_id","property_id")->withPivot("value","params");
	}

	public function propertyById($pid)
	{
		return $this->propertys()->where("property_id",$pid)->where("object_id",$this->id)->first();
	}
	public function fields()
	{
		return DB::table("socials_fields")->get();
	}

	public function allProperties()
	{
		// Получаем все свойства, относящиеся к группе
		$groupFields = $this->fields();  
	
		// Отдельно получаем все актуальные свойства объекта (включая те, что вне группы) со значениями
		$objectProperties = $this->propertys()->get();
	
		// Финальный массив для объединения данных
		$finalResults = [];

		//Массив обработанных групповых
		$processedPropertyIds = [];

		//-------------------------------------- ГРУППОВЫЕ СВОЙСТВА --------------------------------------//

		// Разделяем поля на две части: принадлежащие группе и самостоятельные
		foreach ($groupFields as $field) {

			// Запоминаем id текущего свойства как обработанный
			$processedPropertyIds[] = $field->property_id;

	        // Начинаем формировать массив результата
			$resultItem = [
				'property_id' => $field->property_id,
				'name'        => 'ERROR',
				'desc'        => 'ERROR',
				'value'       => 'ERROR',
				'require'     => 'ERROR',
				'access'      => 'ERROR',
				'isGroupProp' => true,
				'errors'      => [], // Хранится список ошибок
			];

			// Проверяем наличие конкретного свойства в списке общих свойств объекта со значениями
			$relatedProp = $objectProperties->first(function ($prop) use ($field) {
				return $prop->pivot->property_id == $field->property_id;
			});

			//Отсуствует в таблице значение
			if(is_null($relatedProp))
			{
				$resultItem['errors'][] = "Отсуствует значение. Таблица объектов $this->table. Объект: $this->id. ID Свойства: $field->property_id.";
				$resultItem['value'] = '';

				$params = json_decode($field->params,true);
				//Не парсятся значения
				if(is_null($params))
				{
					//Так как не смог декодировать JSON заполняем дефолтными значениями
					$resultItem['errors'][] = "Не смог декодировать params в таблице списка свойств группы. Таблица объектов $this->table. Объект: $this->id. ID Свойства: $field->property_id.";
					$resultItem['errors'][] = "Пробую получить название и описание из общей таблицы свойств.";
					$resultItem['require'] = 0;
					$resultItem['access'] = [];

					$inAllProp = Propertys::find($field->property_id);
					if(is_null($inAllProp))
					{
						$resultItem['errors'][] = "Свойство с ID $field->property_id отсуствует в таблице всех свойств";
						$resultItem['name'] = "Свойство №$field->property_id";
						$resultItem['desc'] = "Описание отсуствует";
					}
					else
					{
						$resultItem['name'] = $inAllProp->name;
						$resultItem['desc'] = $inAllProp->desc;
					}
					$finalResults[] = $resultItem;
					continue;
				}

				$inAllProp = Propertys::find($field->property_id);
				if(!isset($params['name']))
				{
					$resultItem['errors'][] = "Предупреждение: name в таблице списка свойств группы. Таблица объектов $this->table. Объект: $this->id. ID Свойства: $field->property_id.";
					if(is_null($inAllProp))
					{
						$resultItem['errors'][] = "Свойство с ID $field->property_id отсуствует в таблице всех свойств";
						$resultItem['name'] = "Свойство №$field->property_id";
					}
					else
					{
						$resultItem['name'] = $inAllProp->name;
					}
				}else{$resultItem['name']=$params['name'];}

				if(!isset($params['desc']))
				{
					$resultItem['errors'][] = "Предупреждение: desc в таблице списка свойств группы. Таблица объектов $this->table. Объект: $this->id. ID Свойства: $field->property_id.";
					if(is_null($inAllProp))
					{
						$resultItem['errors'][] = "Свойство с ID $field->property_id отсуствует в таблице всех свойств";
						$resultItem['desc'] = "Описание свойства №$field->property_id";
					}
					else
					{
						$resultItem['desc'] = $inAllProp->desc;
					}
				}else{$resultItem['desc']=$params['desc'];}

				if(!isset($params['require']))
				{
					$resultItem['errors'][] = "Предупреждение: require в таблице списка свойств группы. Таблица объектов $this->table. Объект: $this->id. ID Свойства: $field->property_id.";
					$resultItem['require'] = 0;
				}else{$resultItem['require']=$params['require'];}

				if(!isset($params['access']))
				{
					$resultItem['errors'][] = "Предупреждение: access в таблице списка свойств группы. Таблица объектов $this->table. Объект: $this->id. ID Свойства: $field->property_id.";
					$resultItem['access'] = [];
				}else{$resultItem['access']=$params['access'];}
			}

			//Значение есть
			$resultItem['value'] = $relatedProp->pivot->value;
			$params = json_decode($field->params,true);
			//Не парсятся значения
			if(is_null($params))
			{
				//Так как не смог декодировать JSON заполняем дефолтными значениями
				$resultItem['errors'][] = "Не смог декодировать params в таблице списка свойств группы. Таблица объектов $this->table. Объект: $this->id. ID Свойства: $field->property_id.";
				$resultItem['errors'][] = "Пробую получить название и описание из общей таблицы свойств.";
				$resultItem['require'] = 0;
				$resultItem['access'] = [];

				$inAllProp = Propertys::find($field->property_id);
				if(is_null($inAllProp))
				{
					$resultItem['errors'][] = "Свойство с ID $field->property_id отсуствует в таблице всех свойств";
					$resultItem['name'] = "Свойство №$field->property_id";
					$resultItem['desc'] = "Описание отсуствует";
				}
				else
				{
					$resultItem['name'] = $inAllProp->name;
					$resultItem['desc'] = $inAllProp->desc;
				}
				$finalResults[] = $resultItem;
				continue;
			}

			$inAllProp = Propertys::find($field->property_id);
			if(!isset($params['name']))
			{
				$resultItem['errors'][] = "Предупреждение: name в таблице списка свойств группы. Таблица объектов $this->table. Объект: $this->id. ID Свойства: $field->property_id.";
				if(is_null($inAllProp))
				{
					$resultItem['errors'][] = "Свойство с ID $field->property_id отсуствует в таблице всех свойств";
					$resultItem['name'] = "Свойство №$field->property_id";
				}
				else
				{
					$resultItem['name'] = $inAllProp->name;
				}
			}else{$resultItem['name']=$params['name'];}

			if(!isset($params['desc']))
			{
				$resultItem['errors'][] = "Предупреждение: desc в таблице списка свойств группы. Таблица объектов $this->table. Объект: $this->id. ID Свойства: $field->property_id.";
				if(is_null($inAllProp))
				{
					$resultItem['errors'][] = "Свойство с ID $field->property_id отсуствует в таблице всех свойств";
					$resultItem['desc'] = "Описание свойства №$field->property_id";
				}
				else
				{
					$resultItem['desc'] = $inAllProp->desc;
				}
			}else{$resultItem['desc']=$params['desc'];}

			if(!isset($params['require']))
			{
				$resultItem['errors'][] = "Предупреждение: require в таблице списка свойств группы. Таблица объектов $this->table. Объект: $this->id. ID Свойства: $field->property_id.";
				$resultItem['require'] = 0;
			}else{$resultItem['require']=$params['require'];}

			if(!isset($params['access']))
			{
				$resultItem['errors'][] = "Предупреждение: access в таблице списка свойств группы. Таблица объектов $this->table. Объект: $this->id. ID Свойства: $field->property_id.";
				$resultItem['access'] = [];
			}else{$resultItem['access']=$params['access'];}

			$finalResults[] = $resultItem;
		}

		//--------------------------------------НЕ ГРУППОВЫЕ СВОЙСТВА--------------------------------------//

		// Обрабатываем оставшиеся свойства объекта, которые не вошли в первую итерацию
		foreach ($objectProperties as $prop) {

			//Пропуска найденные
			if (in_array($prop->id, $processedPropertyIds)) continue;

	        // Начинаем формировать массив результата
			$resultItem = [
				'property_id' => $field->property_id,
				'name'        => 'ERROR',
				'desc'        => 'ERROR',
				'value'       => 'ERROR',
				'require'     => 'ERROR',
				'access'      => 'ERROR',
				'errors'      => [], // Хранится список ошибок
			];

			//Значение есть
			$resultItem['value'] = $prop->pivot->value;
			//Раз это свойство не относится не групповым, возможно у него есть параметры
			$params = json_decode($prop->params,true);
			//Не парсятся значения
			if(is_null($params))
			{
				//Так как не смог декодировать JSON заполняем дефолтными значениями
				$resultItem['errors'][] = "Не смог декодировать params в таблице значений свойства. Таблица объектов $this->table. Объект: $this->id. ID Свойства: $prop->property_id.";
				$resultItem['errors'][] = "Пробую получить название и описание из общей таблицы свойств.";
				$resultItem['require'] = 0;
				$resultItem['access'] = [];
			}

			if(!isset($params['name']))
			{
				$resultItem['errors'][] = "Предупреждение: name в таблице списка свойств группы. Таблица объектов $this->table. Объект: $this->id. ID Свойства: $prop->property_id.";
				$resultItem['name'] = $prop->name;
			}else{$resultItem['name']=$params['name'];}

			if(!isset($params['desc']))
			{
				$resultItem['errors'][] = "Предупреждение: desc в таблице списка свойств группы. Таблица объектов $this->table. Объект: $this->id. ID Свойства: $field->property_id.";
				$resultItem['desc'] = $prop->desc;
			}else{$resultItem['desc']=$params['desc'];}

			if(!isset($params['require']))
			{
				$resultItem['errors'][] = "Предупреждение: require в таблице списка свойств группы. Таблица объектов $this->table. Объект: $this->id. ID Свойства: $field->property_id.";
				$resultItem['require'] = 0;
			}else{$resultItem['require']=$params['require'];}

			if(!isset($params['access']))
			{
				$resultItem['errors'][] = "Предупреждение: access в таблице списка свойств группы. Таблица объектов $this->table. Объект: $this->id. ID Свойства: $field->property_id.";
				$resultItem['access'] = [];
			}else{$resultItem['access']=$params['access'];}

			$finalResults[] = $resultItem;
		}

		return collect($finalResults); // Возвращаем результат в виде коллекции моделей
	}
}
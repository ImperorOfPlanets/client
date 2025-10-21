<?php
namespace App\Helpers\Logs;

use App\Models\Settings\Logs\LogsModel;
use App\Models\Settings\Site\SettingsModel;

use Illuminate\Support\Facades\Log;

class Logs
{
    public $text;

    public $title;

    public $author;

    public $type;

    public $authors = null;
    public $authorsFinded = null;
    public $propertyFindedAuthors = null;
    public $writeCheck = false;
    public $setedAuthor = false;

    public $logStatus = null;

    public function __construct()
    {
        if($this->authors===null)
        {
            $logObject = SettingsModel::find(31);
            //Включенные авторы
            $propertyAuthors = $logObject->propertyById(102);
            $authorsJson = $propertyAuthors->pivot->value;
            $authors = json_decode($authorsJson);
            if(is_null($authors))
            {
                $this->authors = [];
            }
            else
            {
                $this->authors = $authors;
            }

            //Найденные авторы
            $this->propertyFindedAuthors = $logObject->propertyById(4);
            $authorsFindedJson = $this->propertyFindedAuthors->pivot->value;

            $authorsFinded = json_decode($authorsFindedJson);
            if(is_null($authorsFinded))
            {
                $this->authorsFinded = [];
            }
            else
            {
                $this->authorsFinded = $authorsFinded;
            }
        }
    }

    public function setAuthor($author)
    {
        $this->author = $author;
        if(preg_match('/^\w+/u',$author,$matches))
        {
            //Первое слово автора
            $this->setedAuthor = $matches[0];
            if(!in_array($matches[0],$this->authorsFinded))
            {
                $this->authorsFinded[] = $matches[0];
                $this->propertyFindedAuthors->pivot->value= json_encode($this->authorsFinded);
                $this->propertyFindedAuthors->pivot->save();
            }
        }
        $write = false;
        foreach($this->authors as $author)
        {
            if(str_contains($author,$this->setedAuthor))
            {
                $write  = true;
            }
        }
        $this->writeCheck = $write;
        return $this;
    }

    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function setText($text)
    {
        $this->text=$text;
        return $this;
    }

    public function setType($type)
    {
        $this->type=$type;
        return $this;
    }

    public function write()
    {

        if($this->writeCheck)
        {
            $log = new LogsModel;
            $log->save();
            $log->propertys()->attach(2,['value'=>$this->text ?? 'Без текста']);
            $log->propertys()->attach(12,['value'=>$this->author ?? 'Без автора']);
            if(!is_null($this->type))
            {
                $log->propertys()->attach(107,['value'=>$this->type]);
                $this->type = null;
            }
        }
    }
}
<?php

namespace App\Models\Docs;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Propertys;

class DocsModel extends Model
{
	protected $table = "docs_objects";

	protected $primaryKey = "id";

	protected $casts = [
		"params" => "array",
	];

	protected function asJson($value,$flag=0)
	{
		return json_encode($value,JSON_UNESCAPED_UNICODE);
	}
	public function propertys()
	{
		return $this->belongsToMany(Propertys::class,"docs_propertys","object_id","property_id")->withPivot("value");
	}

	public function propertyById($pid)
	{
		return $this->propertys()->where("property_id",$pid)->where("object_id",$this->id)->first();
	}
}
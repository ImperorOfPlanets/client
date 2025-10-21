<?php

namespace App\Models\Assistant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Propertys;

use DB;

class CommandsModel extends Model
{
	protected $table = "commands_objects";

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
		return $this->belongsToMany(Propertys::class,"commands_propertys","object_id","property_id")->withPivot("value","params");
	}

	public function propertyById($pid)
	{
		return $this->propertys()->where("property_id",$pid)->where("object_id",$this->id)->first();
	}
	public function fields()
	{
		return DB::table("commands_fields")->get();
	}
}
<?php

namespace App\Models\Settings\Site;

use Illuminate\Database\Eloquent\Model;

use App\Models\Propertys;

use Illuminate\Support\Facades\DB;

class SettingsModel extends Model
{
	protected $table = "site_settings_objects";

	protected $primaryKey = "id";

	protected $casts = [
		"params" => "array",
	];

	protected function asJson($value, $flags = 0)
	{
		return json_encode($value,JSON_UNESCAPED_UNICODE);
	}
	public function propertys()
	{
		return $this->belongsToMany(Propertys::class,"site_settings_propertys","object_id","property_id")->withPivot("value","params");
	}

	public function propertyById($pid)
	{
		return $this->propertys()->where("property_id",$pid)->where("object_id",$this->id)->first();
	}
	public function fields()
	{
		return DB::table("site_settings_fields")->get();
	}
}
<?php

namespace App\Helpers\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class JsonCast implements CastsAttributes
{
    public function get($model, $key, $value, $attributes)
    {
        return json_decode($value, true);
    }

    public function set($model, $key, $value, $attributes)
    {
        // 🔒 ДОБАВЛЕНА ЗАЩИТА
        if (!is_array($value) && !is_object($value)) {
            $modelClass = class_basename($model);
            throw new InvalidArgumentException(
                "JsonCast error in {$modelClass}.{$key}: expected array or object, got " . gettype($value)
            );
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
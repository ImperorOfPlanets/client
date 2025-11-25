<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiPromts extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Таблица, связанная с моделью.
     * 
     * @var string
     */
    protected $table = 'ai_prompt_templates'; // Новая таблица для шаблонов промтов

    /**
     * Атрибуты, которые можно массово назначать.
     * 
     * @var array
     */
    protected $fillable = [
        'name',           // Название шаблона (например: "Шаблон генерации описания товара")
        'description',    // Краткое описание цели шаблона
        'settings'        // Настройки шаблона в формате JSON
    ];

    /**
     * Преобразование атрибутов в нативные типы.
     * 
     * @var array
     */
    protected $casts = [
        'settings' => 'array',       // Автоматическое преобразование JSON в массив
        'created_at' => 'datetime',  // Дата создания
        'updated_at' => 'datetime',  // Дата последнего обновления
        'deleted_at' => 'datetime',  // Дата удаления (при soft delete)
    ];

    /**
     * Значения по умолчанию для атрибутов модели.
     * 
     * @var array
     */
    protected $attributes = [
        'settings' => '{
            "text": "",               // Основной текст промта
            "variables": []           // Список переменных шаблона
        }'
    ];

    /**
     * Получает переменные шаблона.
     * 
     * Примеры переменных:
     * "variables": [
     *   {
     *     "name": "product_name",
     *     "type": "string",
     *     "description": "Название продукта",
     *     "required": true
     *   },
     *   {
     *     "name": "price",
     *     "type": "float",
     *     "description": "Цена продукта",
     *     "required": true
     *   }
     * ]
     * 
     * @return array
     */
    public function getVariables(): array
    {
        return $this->settings['variables'] ?? [];
    }

    /**
     * Получает текст шаблона.
     * 
     * @return string
     */
    public function getText(): string
    {
        return $this->settings['text'] ?? '';
    }

    /**
     * Установщик атрибута settings, позволяющий сохранять значения как массив.
     * 
     * @param mixed $value
     */
    public function setSettingsAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['settings'] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } elseif (!empty($value)) {
            $this->attributes['settings'] = $value;
        } else {
            $this->attributes['settings'] = '{}'; // Ставим пустым объектом
        }
    }

    public function getTags(): array
    {
        return $this->settings['tags'] ?? [];
    }

    public function setTags(array $tags)
    {
        $this->settings['tags'] = $tags;
    }
}
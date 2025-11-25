<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiService extends Model
{
    use HasFactory;

    /**
     * Название таблицы в БД
     */
    protected $table = 'ai_services';

    /**
     * Поля, доступные для массового заполнения
     */
    protected $fillable = [
        'name',
        'settings',
        'is_active'
    ];

    /**
     * Преобразование типов атрибутов
     */
    protected $casts = [
        'settings' => 'array', // Автоматическое преобразование JSON в массив
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Связь с запросами AI
     */
    public function requests()
    {
        return $this->hasMany(AiRequest::class, 'service_id');
    }

    /**
     * Проверка активности сервиса
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Получение настроек сервиса
     */
    public function getSettings(): array
    {
        return $this->settings ?? [];
    }
}
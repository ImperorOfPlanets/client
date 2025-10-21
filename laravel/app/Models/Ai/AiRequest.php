<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Helpers\Casts\JsonCast;

class AiRequest extends Model
{
    use HasFactory;

    /**
     * Поля, доступные для массового заполнения.
     */
    protected $fillable = [
        'service_id',
        'request_data',
        'response_data',
        'status',
        'metadata'
    ];

    /**
     * Преобразование типов атрибутов.
     */
    protected $casts = [
        'request_data' => JsonCast::class,
        'response_data' => JsonCast::class,
        'metadata' => JsonCast::class,
    ];

    public static function getStatuses(): array
    {
        return [
            'pending' => 'В ожидании',
            'processing' => 'В обработке',
            'completed' => 'Завершено',
            'failed' => 'Ошибка',
            'retrying' => 'Повторная попытка',
        ];
    }
}
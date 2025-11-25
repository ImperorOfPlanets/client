<?php
namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;

class AiEmbeddings extends Model
{
    protected $fillable = [
        'content',
        'category_id',
        'object_id',
        'status',
        'vector_ids',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'vector_ids' => 'array'
    ];

    public function getStatusAttribute()
    {
        return $this->metadata['processing']['status'] ?? 'pending';
    }

    public function getStatusBadgeAttribute()
    {
        $statuses = [
            'pending' => '<span class="badge bg-secondary">Ожидание</span>',
            'processing' => '<span class="badge bg-warning">Обработка</span>',
            'completed' => '<span class="badge bg-success">Завершено</span>',
            'error' => '<span class="badge bg-danger">Ошибка</span>',
            'needs_update' => '<span class="badge bg-info">Требует обновления</span>'
        ];

        return $statuses[$this->status] ?? $statuses['pending'];
    }

    public function getCategoryNameAttribute()
    {
        $categoryId = $this->metadata['category']['id'] ?? null;
        $categories = [
            1 => 'Товары',
            2 => 'Услуги',
            3 => 'События'
        ];

        return $categoryId ? $categories[$categoryId] : '—';
    }

    public function getTokenCountAttribute()
    {
        return $this->metadata['processing']['token_count'] ?? null;
    }

    public function getChunkCountAttribute()
    {
        return $this->metadata['processing']['chunk_count'] ?? 1;
    }

    public function getVectorIdAttribute()
    {
        // Возвращаем первый ID вектора или null
        return !empty($this->vector_ids) ? $this->vector_ids[0] : null;
    }
}

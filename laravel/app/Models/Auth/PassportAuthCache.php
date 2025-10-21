<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PassportAuthCache extends Model
{
    use HasFactory;

    protected $table = 'passport_auth_cache';
    
    protected $fillable = [
        'provider',
        'provider_user_id', 
        'system_user_id',
        'auth_data',
        'auth_success',
        'last_accessed_at'
    ];

    protected $casts = [
        'auth_data' => 'array',
        'auth_success' => 'boolean',
        'last_accessed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Поиск записи по провайдеру и user_id
     */
    public static function findByProvider(string $provider, string $providerUserId): ?self
    {
        return static::where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();
    }

    /**
     * Создание или обновление записи кеша
     */
    public static function updateOrCreateCache(
        string $provider, 
        string $providerUserId, 
        ?int $systemUserId, 
        array $authData, 
        bool $authSuccess
    ): self {
        return static::updateOrCreate(
            [
                'provider' => $provider,
                'provider_user_id' => $providerUserId
            ],
            [
                'system_user_id' => $systemUserId,
                'auth_data' => $authData,
                'auth_success' => $authSuccess,
                'last_accessed_at' => now()
            ]
        );
    }

    /**
     * Получение валидных записей (использовались менее 24 часов назад)
     */
    public static function getValidEntries(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('last_accessed_at', '>=', now()->subHours(24))->get();
    }

    /**
     * Удаление устаревших записей (не использовались 24+ часов)
     */
    public static function cleanupExpired(): int
    {
        return static::where('last_accessed_at', '<', now()->subHours(24))->delete();
    }

    /**
     * Получение статистики кеша
     */
    public static function getStats(): array
    {
        $total = static::count();
        $successful = static::where('auth_success', true)->count();
        $failed = static::where('auth_success', false)->count();
        $expired = static::where('last_accessed_at', '<', now()->subHours(24))->count();

        return [
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'expired' => $expired,
            'valid' => $total - $expired
        ];
    }
}
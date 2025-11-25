<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('passport_auth_cache', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50); // telegram, vk, etc.
            $table->string('provider_user_id', 100); // ID пользователя в соцсети
            $table->integer('system_user_id')->nullable(); // ID пользователя в системе
            $table->json('auth_data')->nullable(); // Полные данные авторизации
            $table->boolean('auth_success')->default(false); // Успешна ли авторизация
            $table->timestamp('last_accessed_at')->useCurrent(); // Последнее использование
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            // Индексы для быстрого поиска
            $table->index(['provider', 'provider_user_id']);
            $table->index('last_accessed_at');
            $table->index('auth_success');
            
            // Уникальность комбинации провайдер + user_id
            $table->unique(['provider', 'provider_user_id']);
        });
        
        // Таблица для отслеживания запусков очистки
        Schema::create('passport_cache_cleanup_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('records_processed')->default(0);
            $table->integer('records_deleted')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('passport_cache_cleanup_logs');
        Schema::dropIfExists('passport_auth_cache');
    }
};
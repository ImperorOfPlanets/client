<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('ai_services'); // Связь с таблицей ai_services
            $table->json('request_data'); // Данные запроса (промпт, параметры)
            $table->json('response_data')->nullable(); // Ответ от AI-сервиса
            $table->string('status')->default('pending'); // Статус: pending, processing, completed, failed
            $table->json('metadata')->nullable(); // Дополнительные метаданные
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};

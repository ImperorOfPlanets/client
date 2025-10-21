<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_embeddings', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->unsignedTinyInteger('category_id')->nullable()->index();
            $table->string('vector_id')->nullable()->index(); // Будет заполняться Observer'ом
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_embeddings');
    }
};
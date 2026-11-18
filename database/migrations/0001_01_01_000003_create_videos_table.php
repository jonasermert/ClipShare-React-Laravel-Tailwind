<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('videos')) {
            return;
        }

        Schema::create('videos', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('filename');
            $table->string('mime_type', 100);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        // Die Tabelle kann aus der früheren ClipShare-Installation stammen.
        throw new LogicException('Die Videotabelle bleibt bei einem Rollback erhalten.');
    }
};

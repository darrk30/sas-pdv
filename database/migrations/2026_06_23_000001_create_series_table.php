<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tipo');
            $table->string('serie', 20);
            $table->unsignedInteger('numero')->default(1);
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'serie']);
            $table->index(['empresa_id', 'tipo'], 'idx_series_empresa_tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};

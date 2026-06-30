<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lista_deseos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('variante_id')->nullable()->constrained('variantes')->nullOnDelete();
            $table->unsignedInteger('cantidad')->default(1);
            $table->timestamps();

            $table->index(['empresa_id', 'user_id']);
            $table->index(['user_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_deseos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kardex', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
        });

        Schema::table('kardex', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable()->change();
            $table->foreign('empresa_id')
                ->references('id')
                ->on('empresas')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kardex', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
        });

        Schema::table('kardex', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable()->change();
            $table->foreign('empresa_id')
                ->references('id')
                ->on('empresas')
                ->nullOnDelete();
        });
    }
};

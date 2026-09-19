<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->foreignId('repartidor_id')
                ->nullable()
                ->after('mesa_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->dropForeign(['repartidor_id']);
            $table->dropColumn('repartidor_id');
        });
    }
};

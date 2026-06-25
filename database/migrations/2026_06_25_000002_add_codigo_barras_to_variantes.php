<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variantes', function (Blueprint $table) {
            $table->string('codigo_barras', 100)->nullable()->after('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('variantes', function (Blueprint $table) {
            $table->dropColumn('codigo_barras');
        });
    }
};

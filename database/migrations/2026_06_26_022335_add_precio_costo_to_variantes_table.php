<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variantes', function (Blueprint $table) {
            $table->decimal('precio_costo', 12, 4)->nullable()->default(null)->after('precio_final');
        });
    }

    public function down(): void
    {
        Schema::table('variantes', function (Blueprint $table) {
            $table->dropColumn('precio_costo');
        });
    }
};

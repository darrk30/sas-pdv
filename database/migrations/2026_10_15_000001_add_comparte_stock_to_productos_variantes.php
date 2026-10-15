<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('comparte_stock')->default(false)->after('control_de_stock');
        });

        Schema::table('variantes', function (Blueprint $table) {
            $table->decimal('factor_presentacion', 8, 4)->default(1.0)->after('precio_costo');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('comparte_stock');
        });

        Schema::table('variantes', function (Blueprint $table) {
            $table->dropColumn('factor_presentacion');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->foreignId('cupon_id')->nullable()->after('total')
                  ->constrained('cupones')->nullOnDelete();
            $table->decimal('descuento_cupon', 12, 2)->default(0)->after('cupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->dropForeign(['cupon_id']);
            $table->dropColumn(['cupon_id', 'descuento_cupon']);
        });
    }
};

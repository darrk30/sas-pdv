<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_clientes', function (Blueprint $table) {
            // Plan e ciclo que se está pagando
            $table->foreignId('plan_id')->nullable()->after('suscripcion_id')
                ->constrained('plans')->nullOnDelete();
            $table->string('ciclo', 20)->nullable()->after('plan_id')
                ->comment('mensual | anual — ciclo que cubre este pago');

            // Período calculado al momento de la aprobación
            $table->date('periodo_desde')->nullable()->after('concepto');
            $table->date('periodo_hasta')->nullable()->after('periodo_desde');
        });
    }

    public function down(): void
    {
        Schema::table('pagos_clientes', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['plan_id', 'ciclo', 'periodo_desde', 'periodo_hasta']);
        });
    }
};

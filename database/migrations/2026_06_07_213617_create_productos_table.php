<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            // Relaciones
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->foreignId('marca_id')->nullable()->constrained('marcas')->nullOnDelete();
            $table->foreignId('produccion_id')->nullable()->constrained('produccions')->nullOnDelete();
            $table->foreignId('unidad_medida_id')->constrained('unidades_medidas')->cascadeOnDelete();

            // Datos identificadores (Cambio de SKU a codigo_interno)
            $table->string('nombre');
            $table->string('logo')->nullable();
            $table->string('codigo_interno');
            $table->string('codigo_barras')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('slug');

            // Precios y Stock
            $table->decimal('precio_costo', 15, 2)->default(0);
            $table->decimal('precio_venta', 15, 2)->default(0);

            // 🌟 Nuevos Booleanos de Control Operativo
            $table->boolean('es_cortesia')->default(false);
            $table->boolean('visible_en_carta')->default(true);
            $table->boolean('control_de_stock')->default(true);
            $table->boolean('venta_sin_stock')->default(false);
            $table->string('etiqueta')->nullable();
            $table->integer('orden')->default(0);
            $table->string('estado')->default('activo');
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo_interno'], 'idx_empresa_codigo_interno');
            $table->unique(['empresa_id', 'slug'], 'idx_empresa_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};

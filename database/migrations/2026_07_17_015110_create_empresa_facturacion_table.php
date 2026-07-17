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
        Schema::create('empresa_facturacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->unique()->constrained('empresas')->cascadeOnDelete();

            // Credenciales SOL
            $table->string('sol_user', 20);
            $table->text('sol_pass');            // encriptada con encrypt()

            // Certificado digital
            $table->string('cert_path');         // ruta al .pem en el facturador

            // Conexión al FacturadorGreenter
            $table->string('facturador_url');
            $table->string('facturador_api_token');
            $table->boolean('produccion')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresa_facturacion');
    }
};

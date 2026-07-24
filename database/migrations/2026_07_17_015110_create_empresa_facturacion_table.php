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

            // Credenciales SOL (nullable: se pueden configurar después de crear el registro)
            $table->string('sol_user', 20)->nullable();
            $table->text('sol_pass')->nullable();           // encriptada con encrypt()

            // Certificado digital
            $table->string('cert_path')->nullable();
            $table->text('cert_password')->nullable();      // encriptada con encrypt()

            // Conexión al FacturadorGreenter
            $table->string('facturador_url')->nullable();
            $table->string('facturador_api_token')->nullable();
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

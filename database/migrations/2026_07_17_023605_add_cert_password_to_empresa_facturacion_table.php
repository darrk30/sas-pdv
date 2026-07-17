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
        Schema::table('empresa_facturacion', function (Blueprint $table) {
            // Passphrase del certificado .pem (puede estar vacía si el cert no tiene contraseña)
            $table->text('cert_password')->nullable()->after('cert_path');
        });
    }

    public function down(): void
    {
        Schema::table('empresa_facturacion', function (Blueprint $table) {
            $table->dropColumn('cert_password');
        });
    }
};

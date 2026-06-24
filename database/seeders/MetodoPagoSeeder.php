<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\MetodoPago;
use App\Models\User;
use Illuminate\Database\Seeder;

class MetodoPagoSeeder extends Seeder
{
    public function run(): void {}

    public function runForEmpresa(Empresa $empresa): void
    {
        if (MetodoPago::where('empresa_id', $empresa->id)->exists()) {
            return;
        }

        $userId = User::value('id') ?? 1;

        $metodos = ['Efectivo', 'Yape', 'Plin', 'Transferencia'];

        foreach ($metodos as $nombre) {
            MetodoPago::create([
                'empresa_id'          => $empresa->id,
                'user_id'             => $userId,
                'nombre'              => $nombre,
                'requiere_referencia' => false,
                'condicion_pago'      => 'contado',
                'estado'              => 'activo',
                'imagen'              => null,
            ]);
        }
    }
}

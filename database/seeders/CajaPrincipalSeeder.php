<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Empresa;
use App\Models\Turno;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CajaPrincipalSeeder extends Seeder
{
    public function run(): void {}

    public function runForEmpresa(Empresa $empresa): void
    {
        if (Caja::where('empresa_id', $empresa->id)->where('codigo', 'CAJA-001')->exists()) {
            return;
        }

        $caja = Caja::create([
            'empresa_id' => $empresa->id,
            'nombre'     => 'Caja Principal',
            'codigo'     => 'CAJA-001',
            'estado'     => true,
        ]);

        $turnoManana = Turno::where('empresa_id', $empresa->id)
            ->where('nombre', 'MAÑANA')
            ->first();

        if (! $turnoManana) {
            return;
        }

        $userRow = DB::table('empresa_user')
            ->where('empresa_id', $empresa->id)
            ->first();

        if ($userRow) {
            $caja->usuarios()->attach($userRow->user_id, ['turno_id' => $turnoManana->id]);
        }
    }
}

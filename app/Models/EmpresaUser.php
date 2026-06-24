<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EmpresaUser extends Pivot
{
    protected $table = 'empresa_user';

    protected static function booted(): void
    {
        static::created(function (EmpresaUser $pivot): void {
            $caja = Caja::where('empresa_id', $pivot->empresa_id)
                ->where('codigo', 'CAJA-001')
                ->first();

            $turno = Turno::where('empresa_id', $pivot->empresa_id)
                ->where('nombre', 'MAÑANA')
                ->first();

            if ($caja && $turno) {
                // evitar duplicado si ya está vinculado
                $yaVinculado = $caja->usuarios()
                    ->wherePivot('user_id', $pivot->user_id) // fixed: use user_id not model_id
                    ->exists();

                if (! $yaVinculado) {
                    $caja->usuarios()->attach($pivot->user_id, ['turno_id' => $turno->id]);
                }
            }
        });
    }
}

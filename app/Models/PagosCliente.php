<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PagosCliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'suscripcion_id',
        'monto',
        'path_url',
        'fecha_pago',
        'metodo_pago',
        'referencia'
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_pago' => 'datetime',
        ];
    }

    public function suscripcion()
    {
        return $this->belongsTo(Suscripcion::class, 'suscripcion_id');
    }

    public function empresa()
    {
        return $this->hasOneThrough(Empresa::class, Suscripcion::class, 'id', 'id', 'suscripcion_id', 'empresa_id');
    }
}

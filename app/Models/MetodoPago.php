<?php

namespace App\Models;

use App\Enums\CondicionPago;
use App\Enums\EstadoGeneral;
use App\Enums\VisibilidadMetodoPago;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetodoPago extends Model
{
    use BelongsToEmpresa;

    protected $table = 'metodos_pago';

    protected $fillable = [
        'empresa_id',
        'user_id',
        'imagen',
        'nombre',
        'descripcion',
        'visible_en',
        'requiere_referencia',
        'condicion_pago',
        'estado',
    ];

    protected $casts = [
        'requiere_referencia' => 'boolean',
        'condicion_pago'      => CondicionPago::class,
        'visible_en'          => VisibilidadMetodoPago::class,
        'estado'              => EstadoGeneral::class,
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function compraPagos(): HasMany
    {
        return $this->hasMany(CompraPago::class);
    }
}

<?php

namespace App\Models;

use App\Enums\EstadoSunat;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResumenSunat extends Model
{
    use BelongsToEmpresa;

    protected $table = 'resumenes_sunat';

    protected $fillable = [
        'empresa_id',
        'tipo',
        'fecha_referencia',
        'correlativo',
        'hash',
        'ticket_sunat',
        'sunat_error',
        'path_xml',
        'sunat_success',
        'sunat_codigo',
        'sunat_descripcion',
        'sunat_notas',
        'path_cdr_zip',
        'estado_sunat',
        'fecha_envio',
        'fecha_respuesta',
    ];

    protected $casts = [
        'fecha_referencia' => 'date',
        'fecha_envio'      => 'datetime',
        'fecha_respuesta'  => 'datetime',
        'sunat_success'    => 'boolean',
        'estado_sunat'     => EstadoSunat::class,
    ];

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }
}

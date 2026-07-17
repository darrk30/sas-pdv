<?php

namespace App\Models;

use App\Enums\EstadoSunat;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nota extends Model
{
    use BelongsToEmpresa;

    protected $table = 'notas';

    protected $fillable = [
        'empresa_id',
        'venta_id',
        'vendedor_id',
        'tipo',
        'serie_id',
        'correlativo',
        'fecha_emision',
        'motivo_codigo',
        'motivo_descripcion',
        'total',
        'total_letras',
        'qr_data',
        'hash',
        'path_xml',
        'path_pdf',
        'path_cdr_zip',
        'sunat_success',
        'sunat_codigo',
        'sunat_descripcion',
        'sunat_mensaje',
        'sunat_notas',
        'estado_sunat',
        'estado',
        'notas',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'total'         => 'decimal:2',
        'sunat_success' => 'boolean',
        'estado_sunat'  => EstadoSunat::class,
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }
}

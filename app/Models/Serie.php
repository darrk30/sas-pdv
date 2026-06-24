<?php

namespace App\Models;

use App\Enums\TipoComprobante;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Serie extends Model
{
    use BelongsToEmpresa, BelongsToUser;

    protected $fillable = ['empresa_id', 'user_id', 'tipo', 'serie', 'numero', 'estado'];

    protected $casts = [
        'tipo'   => TipoComprobante::class,
        'estado' => 'boolean',
    ];
}

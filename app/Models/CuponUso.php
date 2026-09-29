<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuponUso extends Model
{
    protected $table = 'cupon_usos';

    protected $fillable = [
        'empresa_id',
        'cupon_id',
        'cliente_id',
        'orden_id',
    ];

    public function cupon(): BelongsTo
    {
        return $this->belongsTo(Cupon::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class);
    }
}

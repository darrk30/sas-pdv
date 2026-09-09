<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Piso extends Model
{
    use BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'impresora_id',
        'nombre',
        'orden',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function impresora(): BelongsTo
    {
        return $this->belongsTo(Impresora::class);
    }

    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class)->orderBy('orden');
    }

    public function mesasActivas(): HasMany
    {
        return $this->hasMany(Mesa::class)->where('estado', 'activo')->orderBy('orden');
    }
}

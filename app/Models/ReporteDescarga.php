<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteDescarga extends Model
{
    protected $fillable = [
        'empresa_id',
        'user_id',
        'tipo',
        'reporte',
        'estado',
        'filtros',
        'path',
        'nombre_archivo',
        'error_mensaje',
        'expires_at',
    ];

    protected $casts = [
        'filtros'    => 'array',
        'expires_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPendiente(): bool  { return $this->estado === 'pendiente'; }
    public function isProcesando(): bool { return $this->estado === 'procesando'; }
    public function isListo(): bool      { return $this->estado === 'listo'; }
    public function isError(): bool      { return $this->estado === 'error'; }
    public function isExpirado(): bool   { return $this->expires_at && $this->expires_at->isPast(); }
}

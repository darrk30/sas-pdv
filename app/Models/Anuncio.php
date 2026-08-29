<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Anuncio extends Model
{
    protected $table = 'anuncios';

    protected $fillable = [
        'titulo',
        'mensaje',
        'tipo',
        'fecha_inicio',
        'fecha_fin',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo'       => 'boolean',
            'fecha_inicio' => 'datetime',
            'fecha_fin'    => 'datetime',
        ];
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query
            ->where('activo', true)
            ->where(fn ($q) => $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', now()))
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now()));
    }
}

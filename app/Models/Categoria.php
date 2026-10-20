<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    use BelongsToEmpresa;

    protected $fillable = ['nombre', 'imagen_url', 'estado', 'activo_en_pdv', 'activo_en_catalogo', 'empresa_id', 'orden'];

    protected $casts = [
        'estado'            => 'boolean',
        'activo_en_pdv'     => 'boolean',
        'activo_en_catalogo'=> 'boolean',
    ];

    /** Visible en PDV: el estado general manda; luego activo_en_pdv */
    public function getActivaEnPdvAttribute(): bool
    {
        return (bool) $this->estado && (bool) $this->activo_en_pdv;
    }

    /** Visible en catálogo: el estado general manda; luego activo_en_catalogo */
    public function getActivaEnCatalogoAttribute(): bool
    {
        return (bool) $this->estado && (bool) $this->activo_en_catalogo;
    }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }
}

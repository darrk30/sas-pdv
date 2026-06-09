<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Marca extends Model
{
    use BelongsToEmpresa;

    protected $fillable = ['empresa_id', 'nombre', 'logo', 'estado'];

    protected $casts = ['estado' => 'boolean'];

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }
}

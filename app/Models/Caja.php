<?php

namespace App\Models;

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Caja extends Model
{
    use BelongsToEmpresa;

    protected $fillable = ['empresa_id', 'nombre', 'codigo', 'estado', 'impresora_id'];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function impresora(): BelongsTo
    {
        return $this->belongsTo(Impresora::class);
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'caja_usuario')
            ->withPivot('turno_id')
            ->withTimestamps();
    }
}

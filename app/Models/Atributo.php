<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Atributo extends Model
{
    use BelongsToEmpresa; // Trait que hicimos antes

    public function valores()
    {
        return $this->hasMany(Valor::class);
    }
}

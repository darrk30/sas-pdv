<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use BelongsToEmpresa;
    protected $guarded = [];
    
    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
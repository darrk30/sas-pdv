<?php

namespace App\Traits;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait BelongsToEmpresa
{
    protected static function bootBelongsToEmpresa()
    {
        // 1. Filtro Global (Ver datos)
        static::addGlobalScope('empresa', function (Builder $query) {
            
            // Obtenemos el panel de forma segura (si no estamos en web/Filament, devuelve null)
            $panel = app()->bound('filament') ? filament()->getCurrentPanel() : null;
            
            // Si hay un panel activo y NO es el panel principal ('admin')
            if ($panel && $panel->getId() !== 'admin') {
                $tenant = filament()->getTenant();
                
                if ($tenant) {
                    $tabla = (new static)->getTable();
                    $query->where($tabla . '.empresa_id', $tenant->id);
                }
            }
        });

        // 2. Auto-asignación (Crear datos)
        static::creating(function ($model) {
            
            $panel = app()->bound('filament') ? filament()->getCurrentPanel() : null;

            if ($panel && $panel->getId() !== 'admin') {
                $tenant = filament()->getTenant();
                
                if ($tenant) {
                    $model->empresa_id = $tenant->id;
                }
            }
        });
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lógica centralizada para filtrar registros por propietario en contexto multi-tenant.
 *
 * Regla: el usuario solo ve sus propios registros SALVO que sea admin.
 * Admin global = rol cuya definición no pertenece a ninguna empresa (roles.empresa_id IS NULL).
 * También acepta el permiso "ver.registros.todos" para admins de empresa.
 *
 * NOTA: No se usa $user->roles()->whereNull('empresa_id') porque Spatie Permission
 * con team support agrega condiciones propias de empresa_id al JOIN, causando
 * "Column 'empresa_id' in where clause is ambiguous".
 * Se consulta directamente el pivote model_has_roles para evitar ese conflicto.
 */
class OwnerScope
{
    private static ?bool $cache = null;

    /**
     * True si el usuario puede ver registros de todos los usuarios.
     * Resultado cacheado por request para evitar múltiples queries.
     */
    public static function esAdmin(): bool
    {
        if (static::$cache !== null) return static::$cache;

        $user = auth()->user();
        if (! $user) return static::$cache = false;

        // Admin global: query directa al pivote para evitar el scope de teams de Spatie
        $esGlobal = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', get_class($user))
            ->where('model_has_roles.model_id', $user->id)
            ->whereNull('roles.empresa_id')
            ->exists();

        if ($esGlobal) return static::$cache = true;

        // Permiso explícito para gerentes/admins de empresa
        try {
            return static::$cache = $user->can('ver.registros.todos');
        } catch (\Throwable) {
            return static::$cache = false;
        }
    }

    /** Limpia el caché (útil en tests). */
    public static function flush(): void { static::$cache = null; }

    /**
     * Aplica el filtro de propietario a la query si el usuario no es admin.
     */
    public static function aplicar(Builder $query, string $columna = 'user_id'): Builder
    {
        if (! static::esAdmin()) {
            $query->where($columna, auth()->id());
        }

        return $query;
    }
}

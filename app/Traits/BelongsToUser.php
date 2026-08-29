<?php

namespace App\Traits;

use App\Helpers\OwnerScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::creating(function ($model): void {
            if (auth()->check() && empty($model->user_id)) {
                $model->user_id = auth()->id();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: filtra por usuario actual salvo que sea admin.
     * Uso: Model::forCurrentUser()->where(...)
     */
    public function scopeForCurrentUser(Builder $query, string $columna = 'user_id'): Builder
    {
        return OwnerScope::aplicar($query, $columna);
    }
}

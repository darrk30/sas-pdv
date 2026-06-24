<?php

namespace App\Models;

use App\Enums\EstadoGeneral;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasTenants, HasName, FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    // Eliminamos el 'use BelongsToEmpresa;' de aquí

    protected $fillable = [
        'name',
        'email',
        'password',
        'estado',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'estado' => EstadoGeneral::class,
        ];
    }

    // --- RELACIONES ---

    public function empresas()
    {
        return $this->belongsToMany(Empresa::class)
            ->using(EmpresaUser::class)
            ->withPivot('estado');
    }

    // --- FILAMENT MULTI-TENANCY ---

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->empresas;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->empresas()->whereKey($tenant)->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function cajas(): BelongsToMany
    {
        return $this->belongsToMany(Caja::class, 'caja_usuario')
            ->withPivot('turno_id')
            ->withTimestamps();
    }

    public function turnos()
    {
        return $this->belongsToMany(Turno::class, 'caja_usuario');
    }
}

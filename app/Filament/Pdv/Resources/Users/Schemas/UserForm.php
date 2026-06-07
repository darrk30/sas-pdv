<?php

namespace App\Filament\Pdv\Resources\Users\Schemas;

use App\Enums\EstadoGeneral;
use App\Models\Role;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $miSucursalId = Auth::user()->empresas()->first()?->id;
        return $schema
            ->components([
                // Contenedor principal responsive
                Grid::make()
                    ->schema([
                        Group::make()
                            ->schema([
                                Section::make('Información Personal')
                                    ->description('Datos básicos del empleado')
                                    ->icon('heroicon-o-user')
                                    ->columns(['default' => 1, 'sm' => 2])
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nombre Completo')
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('email')
                                            ->label('Correo Electrónico')
                                            ->email()
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255),
                                    ]),

                                Section::make('Seguridad de la Cuenta')
                                    ->description('Gestión de contraseñas de acceso')
                                    ->icon('heroicon-o-lock-closed')
                                    ->columns(['default' => 1, 'sm' => 2])
                                    ->schema([
                                        Toggle::make('change_password')
                                            ->label('Cambiar contraseña de este usuario')
                                            ->live()
                                            ->dehydrated(false)
                                            ->visible(fn(string $context): bool => $context === 'edit')
                                            ->columnSpanFull(),

                                        TextInput::make('password')
                                            ->label(fn(string $context) => $context === 'create' ? 'Contraseña' : 'Nueva Contraseña')
                                            ->password()
                                            ->revealable()
                                            ->confirmed()
                                            ->dehydrateStateUsing(fn($state) => Hash::make($state))
                                            ->dehydrated(fn(?string $state): bool => filled($state))
                                            ->required(fn(string $context, Get $get): bool => $context === 'create' || $get('change_password') === true)
                                            ->visible(fn(string $context, Get $get): bool => $context === 'create' || $get('change_password') === true),

                                        TextInput::make('password_confirmation')
                                            ->label('Confirmar Contraseña')
                                            ->password()
                                            ->revealable()
                                            ->dehydrated(false)
                                            ->required(fn(string $context, Get $get): bool => $context === 'create' || $get('change_password') === true)
                                            ->visible(fn(string $context, Get $get): bool => $context === 'create' || $get('change_password') === true),
                                    ]),
                            ])
                            ->columnSpan(['default' => 1, 'lg' => 2]),

                        // --- COLUMNA DERECHA (Ocupa 1 de 3 espacios) ---
                        // Usamos Group aquí también
                        Group::make()
                            ->schema([
                                Section::make('Accesos y Permisos')
                                    ->description('Control de roles y estado')
                                    ->icon('heroicon-o-shield-check')
                                    ->columns(['default' => 1, 'sm' => 2])
                                    ->schema([
                                        Select::make('roles')
                                            ->label('Rol en Tienda')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->options(function () use ($miSucursalId) {
                                                return Role::where('empresa_id', $miSucursalId)->pluck('name', 'name');
                                            })
                                            ->loadStateFromRelationshipsUsing(function ($component, $state, User $record) use ($miSucursalId) {
                                                if ($record->exists) {
                                                    app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($miSucursalId);
                                                    $component->state($record->roles()->pluck('name')->toArray());
                                                }
                                            })
                                            ->saveRelationshipsUsing(function (User $record, $state) use ($miSucursalId) {
                                                app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($miSucursalId);
                                                $record->syncRoles($state ?? []);
                                            })
                                            ->dehydrated(false),

                                        Select::make('pivot_estado')
                                            ->label('Estado de Acceso')
                                            ->options(EstadoGeneral::class)
                                            ->default(EstadoGeneral::Activo)
                                            ->required()
                                            ->dehydrated(false),
                                    ])
                            ])
                            ->columnSpan(['default' => 1, 'lg' => 2]),
                    ])->columnSpanFull(),
            ]);
    }
}

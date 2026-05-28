<?php

namespace App\Filament\Resources\Empresas\Schemas;

use App\Models\Role;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class EmpresaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Usamos Tabs para separar visualmente lo crítico de lo administrativo
                Tabs::make('Configuración Empresa')
                    ->tabs([
                        Tab::make('General')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('name')->label('Nombre de la Empresa')->required()->columnSpan(2),
                                    TextInput::make('ruc')->label('RUC')->required()->maxLength(11)->numeric(),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('email')->label('Email de Contacto')->email(),
                                    TextInput::make('telefono')->label('Teléfono')->tel(),
                                    TextInput::make('slug')->label('URL amigable (Slug)')->required()->unique(ignoreRecord: true),
                                    Select::make('estado')->options(['activo' => 'Activo', 'inactivo' => 'Inactivo'])->default('activo')->native(false),
                                ]),
                                FileUpload::make('logo')->label('Logotipo')->image()->directory('logos')->columnSpanFull(),
                            ]),

                        Tab::make('Ubicación y Facturación')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                TextInput::make('direccion')->label('Dirección Completa')->columnSpanFull(),
                                Grid::make(3)->schema([
                                    TextInput::make('departamento'),
                                    TextInput::make('provincia'),
                                    TextInput::make('distrito'),
                                ]),
                                Grid::make(3)->schema([
                                    TextInput::make('ubigeo'),
                                    TextInput::make('country_code')->label('Cod. País')->default('PE'),
                                    TextInput::make('cod_local')->label('Cod. Local')->default('0000'),
                                ]),
                            ]),

                        Tab::make('Sistema')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('carta_activa_cliente')
                                        ->label('Carta Activa Cliente')
                                        ->options(['activo' => 'Activo', 'inactivo' => 'Inactivo'])
                                        ->native(false),
                                    Select::make('carta_activa_admin')
                                        ->label('Carta Activa Admin')
                                        ->options(['activo' => 'Activo', 'inactivo' => 'Inactivo'])
                                        ->native(false),
                                ]),
                            ]),
                        Tab::make('Usuarios y Roles')
                            ->icon('heroicon-o-users')
                            ->schema([
                                Repeater::make('usuarios')
                                    ->label('Usuarios Asignados')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('user_id')
                                                ->label('Usuario')
                                                ->options(User::all()->pluck('name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->createOptionForm([
                                                    TextInput::make('name')->required(),
                                                    TextInput::make('email')->email()->required(),
                                                    TextInput::make('password')->password()->required(),
                                                ])
                                                ->createOptionUsing(function (array $data) {
                                                    return User::create([
                                                        'name' => $data['name'],
                                                        'email' => $data['email'],
                                                        'password' => Hash::make($data['password']),
                                                    ])->id;
                                                }),

                                            Select::make('roles')
                                                ->label('Rol')
                                                ->options(Role::all()->pluck('name', 'id'))
                                                ->multiple()
                                                ->preload()
                                                ->native(false),
                                        ]),
                                    ])
                                    ->itemLabel(fn(array $state): ?string => User::find($state['user_id'])?->name ?? 'Nuevo Usuario')
                                    ->addActionLabel('Vincular usuario')
                                    ->columnSpanFull()
                                    ->afterStateHydrated(function ($component, $state, $record) {
                                        if ($record) {
                                            $data = $record->usuarios->map(fn($user) => [
                                                'user_id' => $user->id,
                                                'roles' => $user->roles->pluck('id')->toArray(),
                                            ])->toArray();
                                            $component->state($data);
                                        }
                                    })
                            ]),
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
            ]);
    }
}

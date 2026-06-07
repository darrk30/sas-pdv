<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\EstadoGeneral;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Usuario')
                    ->description('Datos personales y acceso al sistema')
                    ->icon('heroicon-o-user')
                    ->collapsible()
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
                        Select::make('estado')
                            ->options(EstadoGeneral::class)
                            ->default(EstadoGeneral::Activo)
                    ])
                    ->columns(2),
                Section::make('Seguridad')
                    ->schema([
                        Select::make('roles')
                            ->label('Roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->required(),
                        // Este toggle solo aparece si estamos editando
                        Toggle::make('change_password')
                            ->label('¿Cambiar contraseña?')
                            ->visible(fn($context) => $context === 'edit')
                            ->reactive(),

                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->required(fn($context) => $context === 'create')
                            ->visible(fn(Get $get, $context) => $context === 'create' || $get('change_password'))
                            ->dehydrateStateUsing(fn($state) => Hash::make($state))
                            ->dehydrated(fn($state) => filled($state))
                            ->confirmed(), // Habilita la validación de confirmación

                        TextInput::make('password_confirmation')
                            ->label('Confirmar Contraseña')
                            ->password()
                            ->revealable()
                            ->required(fn(Get $get, $context) => $context === 'create' || $get('change_password'))
                            ->visible(fn(Get $get, $context) => $context === 'create' || $get('change_password'))
                            ->dehydrated(false), // No guardar la confirmación en la BD
                    ])
                    ->columns(2),
            ]);
    }
}

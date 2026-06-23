<?php

namespace App\Filament\Pdv\Resources\Proveedores\Schemas;

use App\Enums\EstadoGeneral;
use App\Enums\TipoDocumento;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProveedorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Datos del proveedor')
                    ->columns(2)
                    ->schema([

                        TextInput::make('nombre')
                            ->label('Nombre / Razón social')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('tipo_documento')
                            ->label('Tipo de documento')
                            ->options(TipoDocumento::class)
                            ->required()
                            ->native(false),

                        TextInput::make('numero_documento')
                            ->label('Número de documento')
                            ->required()
                            ->maxLength(20),

                        TextInput::make('correo')
                            ->label('Correo electrónico')
                            ->email()
                            ->nullable()
                            ->maxLength(255),

                        TextInput::make('telefono')
                            ->label('Teléfono')
                            ->tel()
                            ->nullable()
                            ->maxLength(20),

                        TextInput::make('direccion')
                            ->label('Dirección')
                            ->nullable()
                            ->maxLength(255),

                        TextInput::make('departamento')
                            ->label('Departamento')
                            ->nullable()
                            ->maxLength(100),

                        Select::make('estado')
                            ->label('Estado')
                            ->options(EstadoGeneral::class)
                            ->required()
                            ->native(false)
                            ->default(EstadoGeneral::Activo->value),

                    ]),

            ]);
    }
}

<?php

namespace App\Filament\Pdv\Resources\MetodosPago\Schemas;

use App\Enums\CondicionPago;
use App\Enums\EstadoGeneral;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MetodoPagoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Datos del método de pago')
                    ->columns(2)
                    ->schema([

                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        FileUpload::make('imagen')
                            ->label('Imagen / Logo')
                            ->image()
                            ->directory('metodos-pago')
                            ->nullable()
                            ->columnSpanFull(),

                        Select::make('condicion_pago')
                            ->label('Condición de pago')
                            ->options(CondicionPago::class)
                            ->required()
                            ->native(false)
                            ->default(CondicionPago::Contado->value),

                        Select::make('estado')
                            ->label('Estado')
                            ->options(EstadoGeneral::class)
                            ->required()
                            ->native(false)
                            ->default(EstadoGeneral::Activo->value),

                        Toggle::make('requiere_referencia')
                            ->label('Requiere referencia')
                            ->helperText('Al activar, el campo referencia será obligatorio al registrar un pago.')
                            ->default(false)
                            ->columnSpanFull(),

                    ]),

            ]);
    }
}

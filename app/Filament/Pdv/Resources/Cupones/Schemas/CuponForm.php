<?php

namespace App\Filament\Pdv\Resources\Cupones\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CuponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Datos del cupón')
                ->columns(2)
                ->schema([

                    TextInput::make('codigo')
                        ->label('Código')
                        ->placeholder('Ej: VERANO25')
                        ->required()
                        ->maxLength(50)
                        ->helperText('Se guardará en mayúsculas automáticamente.'),

                    TextInput::make('descripcion')
                        ->label('Descripción')
                        ->placeholder('Ej: Descuento de verano 25%')
                        ->maxLength(255)
                        ->nullable(),

                ]),

            Section::make('Descuento')
                ->columns(2)
                ->schema([

                    Select::make('tipo_descuento')
                        ->label('Tipo de descuento')
                        ->options([
                            'porcentaje' => 'Porcentaje (%)',
                            'monto_fijo' => 'Monto fijo (S/)',
                        ])
                        ->native(false)
                        ->default('porcentaje')
                        ->required()
                        ->live(),

                    TextInput::make('valor')
                        ->label(fn (\Filament\Schemas\Components\Utilities\Get $get) =>
                            $get('tipo_descuento') === 'porcentaje' ? 'Descuento (%)' : 'Descuento (S/)'
                        )
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(fn (\Filament\Schemas\Components\Utilities\Get $get) =>
                            $get('tipo_descuento') === 'porcentaje' ? 100 : null
                        )
                        ->suffix(fn (\Filament\Schemas\Components\Utilities\Get $get) =>
                            $get('tipo_descuento') === 'porcentaje' ? '%' : 'S/'
                        )
                        ->required(),

                    TextInput::make('monto_minimo')
                        ->label('Monto mínimo del pedido')
                        ->numeric()
                        ->prefix('S/')
                        ->minValue(0)
                        ->nullable()
                        ->helperText('Dejar vacío para aplicar a cualquier monto.'),

                    TextInput::make('cantidad_minima')
                        ->label('Cantidad mínima de productos')
                        ->numeric()
                        ->minValue(1)
                        ->nullable()
                        ->helperText('Ej: 5 → se activa al comprar 5 o más productos (cualquiera).'),

                ]),

            Section::make('Límites de uso')
                ->columns(3)
                ->schema([

                    TextInput::make('stock')
                        ->label('Stock de usos')
                        ->numeric()
                        ->minValue(1)
                        ->nullable()
                        ->helperText('Dejar vacío para usos ilimitados.'),

                    TextInput::make('usos')
                        ->label('Usos acumulados')
                        ->numeric()
                        ->default(0)
                        ->disabled()
                        ->dehydrated(false),

                    TextInput::make('usos_por_cliente')
                        ->label('Máx. usos por cliente')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),

                ]),

            Section::make('Vigencia')
                ->columns(2)
                ->schema([

                    DatePicker::make('fecha_inicio')
                        ->label('Fecha de inicio')
                        ->displayFormat('d/m/Y')
                        ->nullable(),

                    DatePicker::make('fecha_fin')
                        ->label('Fecha de fin')
                        ->displayFormat('d/m/Y')
                        ->nullable()
                        ->helperText('Dejar vacío para que no expire.'),

                    Toggle::make('activo')
                        ->label('Cupón activo')
                        ->default(true)
                        ->columnSpanFull(),

                ]),

        ]);
    }
}

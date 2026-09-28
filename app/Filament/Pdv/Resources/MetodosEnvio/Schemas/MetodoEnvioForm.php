<?php

namespace App\Filament\Pdv\Resources\MetodosEnvio\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MetodoEnvioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del método de envío')
                    ->columns(2)
                    ->schema([

                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->placeholder('Ej: AGENCIA SHALOM')
                            ->required()
                            ->maxLength(255),

                        RichEditor::make('descripcion')
                            ->label('Descripción')
                            ->placeholder('Ej: 3 a 5 días hábiles')
                            ->nullable()
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList'])
                            ->columnSpanFull(),

                        TextInput::make('costo')
                            ->label('Costo')
                            ->numeric()
                            ->prefix('S/')
                            ->minValue(0)
                            ->default(0)
                            ->required(),

                        Select::make('estado')
                            ->label('Estado')
                            ->options(['activo' => 'Activo', 'inactivo' => 'Inactivo'])
                            ->native(false)
                            ->default('activo')
                            ->required(),

                        Select::make('tipo')
                            ->label('Tipo de envío')
                            ->options([
                                'delivery'   => 'Delivery (entrega en domicilio del cliente)',
                                'provincial' => 'Provincial (envío a agencia de transporte)',
                                'retiro'     => 'Retiro en tienda (el cliente recoge)',
                            ])
                            ->native(false)
                            ->default('delivery')
                            ->required()
                            ->live()
                            ->helperText('Delivery: el repartidor lleva el pedido. Provincial: el cliente recoge en agencia. Retiro: el cliente viene a la tienda.')
                            ->columnSpanFull(),

                        TextInput::make('direccion_retiro')
                            ->label('Dirección de recojo')
                            ->placeholder('Ej: Av. Principal 123, Piso 2 — preguntar por almacén')
                            ->nullable()
                            ->maxLength(255)
                            ->helperText('Esta dirección se mostrará al cliente cuando seleccione este método.')
                            ->columnSpanFull()
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('tipo') === 'retiro'),

                    ]),
            ]);
    }
}

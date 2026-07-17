<?php

namespace App\Filament\Resources\Plans\Schemas;

use App\Enums\EstadoGeneral;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Plan')
                    ->description('Datos principales y configuración de cobro')
                    ->icon('heroicon-o-document-text')
                    ->columns([
                        'default' => 1, // 1 columna en celulares
                        'sm' => 2,      // 2 columnas en tablets
                        'lg' => 3,      // 3 columnas en pantallas grandes (PC)
                    ])
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre del Plan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan([
                                'default' => 1, // Ocupa toda la fila en celular
                                'sm' => 2,      // Ocupa 2 columnas en tablet
                                'lg' => 2,      // Ocupa 2 columnas en PC
                            ]),

                        Select::make('estado')
                            ->label('Estado')
                            ->options(EstadoGeneral::class)
                            ->default(EstadoGeneral::Activo)
                            ->required()
                            ->columnSpan(1), // Ocupa 1 columna siempre, adaptándose a su fila

                        TextInput::make('precio')
                            ->label('Precio')
                            ->required()
                            ->numeric()
                            ->prefix('S/')
                            ->columnSpan(1),

                        Select::make('ciclo_facturacion')
                            ->label('Ciclo de Facturación')
                            ->options([
                                'mensual' => 'Mensual',
                                'anual' => 'Anual',
                            ])
                            ->default('mensual')
                            ->required()
                            ->columnSpan(1),

                        Textarea::make('descripcion')
                            ->label('Descripción')
                            ->rows(3) // Le da una altura inicial más agradable
                            ->columnSpanFull(),
                    ])->columnSpanFull(),

                Section::make('Límites del Plan')
                    ->description('Cantidad máxima de recursos que puede tener la empresa')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->collapsible()
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                    ])
                    ->schema([
                        TextInput::make('maximo_usuarios')
                            ->label('Máximo de Usuarios')
                            ->helperText('Usuarios que pueden acceder al panel PDV')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->columnSpan(1),

                        TextInput::make('maximo_locales')
                            ->label('Máximo de Locales')
                            ->helperText('Sucursales o puntos de venta habilitados')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->columnSpan(1),
                    ])->columnSpanFull(),

                Section::make('Funcionalidades incluidas')
                    ->description('Módulos y características que se activan con este plan')
                    ->icon('heroicon-o-puzzle-piece')
                    ->collapsible()
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                    ])
                    ->schema([
                        Toggle::make('tiene_catalogo_web')
                            ->label('Catálogo Web / Tienda Online')
                            ->helperText('Permite activar la tienda online y recibir órdenes de clientes')
                            ->onColor('success')
                            ->default(false)
                            ->columnSpan(1),

                        Toggle::make('tiene_variantes')
                            ->label('Variantes de Productos')
                            ->helperText('Permite crear productos con tallas, colores u otras variantes')
                            ->onColor('success')
                            ->default(false)
                            ->columnSpan(1),

                        Toggle::make('facturacion_electronica')
                            ->label('Facturación Electrónica')
                            ->helperText('Permite emitir comprobantes electrónicos (boletas y facturas)')
                            ->onColor('success')
                            ->default(false)
                            ->columnSpan(1),
                    ])->columnSpanFull(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Plans\Schemas;

use App\Enums\EstadoGeneral;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                    ->description('Restricciones aplicadas a la empresa que contrate este plan')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->collapsible()
                    ->columns([
                        'default' => 1, // 1 columna en celulares
                        'sm' => 2,      // 2 columnas desde tablets en adelante
                    ])
                    ->schema([
                        TextInput::make('maximo_usuarios')
                            ->label('Máximo de Usuarios')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->columnSpan(1),

                        TextInput::make('maximo_locales')
                            ->label('Máximo de Locales')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->columnSpan(1),
                    ])->columnSpanFull(),
            ]);
    }
}

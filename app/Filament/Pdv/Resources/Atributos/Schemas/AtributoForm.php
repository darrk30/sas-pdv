<?php

namespace App\Filament\Pdv\Resources\Atributos\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AtributoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 3,
            ])
            ->components([

                // --- BLOQUE IZQUIERDO: DETALLES DEL ATRIBUTO (Ocupa 2 columnas) ---
                Group::make()
                    ->schema([
                        Section::make('Información del Atributo')
                            ->description('Define las características o variaciones para tus productos.')
                            ->icon('heroicon-o-swatch')
                            ->columns(2) // Ponemos el nombre y el tipo lado a lado
                            ->schema([
                                TextInput::make('nombre')
                                    ->label('Nombre del Atributo')
                                    ->placeholder('Ej. Talla, Color, Sabor, Material')
                                    ->required()
                                    ->maxLength(255),

                                // 🌟 Selector de Tipo Reactivo
                                Select::make('tipo')
                                    ->label('Tipo de Atributo')
                                    ->options([
                                        'texto' => 'Texto',
                                        'color' => 'Color',
                                    ])
                                    ->required()
                                    ->default('texto')
                                    ->live(), // Hace que todo el formulario reaccione al cambiar esta opción
                            ]),

                        Section::make('Opciones Disponibles')
                            ->description('Agrega los valores para este atributo.')
                            ->schema([
                                Repeater::make('valores')
                                    ->relationship()
                                    ->schema([
                                        TextInput::make('nombre')
                                            ->label('Nombre del Valor')
                                            ->required()
                                            ->placeholder(fn($get) => $get('../../tipo') === 'color' ? 'Ej. Rojo' : 'Ej. S, M, L')
                                            ->columnSpan(fn($get) => $get('../../tipo') === 'color' ? 1 : 2),

                                        // 🌟 SOLO DEJAMOS ESTE CAMPO PARA EL COLOR
                                        ColorPicker::make('valor')
                                            ->label('Código Color (HEX)')
                                            // Solo es requerido y visible si el tipo es color
                                            ->required(fn($get) => $get('../../tipo') === 'color')
                                            ->visible(fn($get) => $get('../../tipo') === 'color')
                                            ->columnSpan(1),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('Añadir opción')
                                    ->collapsible()

                                    // 🌟 LA MAGIA 1: Antes de CREAR el registro en BD
                                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                        // Si el campo 'valor' viene vacío (porque el ColorPicker estaba oculto), copiamos el 'nombre'
                                        if (empty($data['valor'])) {
                                            $data['valor'] = $data['nombre'];
                                        }
                                        return $data;
                                    })

                                    // 🌟 LA MAGIA 2: Antes de ACTUALIZAR el registro en BD
                                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                        if (empty($data['valor'])) {
                                            $data['valor'] = $data['nombre'];
                                        }
                                        return $data;
                                    }),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                // --- BLOQUE DERECHO: VISIBILIDAD (Ocupa 1 columna) ---
                Group::make()
                    ->schema([
                        Section::make('Estado')
                            ->description('Control de uso')
                            ->icon('heroicon-o-eye')
                            ->schema([
                                Toggle::make('estado')
                                    ->label('Atributo Activo')
                                    ->helperText('Desactívalo si ya no quieres usarlo en nuevos productos.')
                                    ->default(true),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ]);
    }
}

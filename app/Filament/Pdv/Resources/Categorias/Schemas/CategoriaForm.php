<?php

namespace App\Filament\Pdv\Resources\Categorias\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoriaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // 1. Configuramos las 3 columnas en el nivel raíz del esquema
            ->columns([
                'default' => 1,
                'lg' => 3, // Usamos 'lg' para que en tablets grandes y PC se vea de lado
            ])
            ->components([
                
                // --- BLOQUE IZQUIERDA: INFORMACIÓN GENERAL (Toma 2 columnas) ---
                Group::make()
                    ->schema([
                        Section::make('Información general')
                            ->description('Datos principales de la categoría')
                            ->icon('heroicon-o-tag')
                            ->schema([
                                TextInput::make('nombre')
                                    ->label('Nombre de la Categoría')
                                    ->required()
                                    ->maxLength(255),

                                FileUpload::make('imagen_url')
                                    ->label('Imagen de la Categoría')
                                    ->image()
                                    ->disk('public')
                                    ->directory('categorias')
                                    ->optimize('webp', 88)
                                    ->maxImageWidth(600)
                                    ->imageEditor(),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]), // Esto empuja a la derecha al siguiente componente

                // --- BLOQUE DERECHA: VISIBILIDAD (Toma 1 columna) ---
                Group::make()
                    ->schema([
                        Section::make('Visibilidad')
                            ->description('Control de estado')
                            ->icon('heroicon-o-eye')
                            ->schema([
                                Toggle::make('estado')
                                    ->label('Categoría Activa')
                                    ->helperText('Si se desactiva, los productos se ocultarán.')
                                    ->default(true),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ]);
    }
}
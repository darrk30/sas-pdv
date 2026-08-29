<?php

namespace App\Filament\Resources\Anuncios;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AnuncioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contenido del anuncio')
                ->columns(2)
                ->schema([
                    TextInput::make('titulo')
                        ->label('Título')
                        ->required()
                        ->maxLength(100)
                        ->columnSpanFull(),

                    Textarea::make('mensaje')
                        ->label('Mensaje')
                        ->required()
                        ->rows(3)
                        ->maxLength(500)
                        ->columnSpanFull(),

                    Select::make('tipo')
                        ->label('Tipo')
                        ->options([
                            'info'        => 'ℹ️ Información',
                            'advertencia' => '⚠️ Advertencia',
                            'peligro'     => '🚨 Peligro / Mantenimiento',
                            'exito'       => '✅ Éxito / Novedad',
                        ])
                        ->default('info')
                        ->native(false)
                        ->required(),

                    Toggle::make('activo')
                        ->label('Activo')
                        ->default(true)
                        ->onColor('success'),
                ]),

            Section::make('Vigencia')
                ->description('Opcional — deja vacío para que no tenga fecha límite')
                ->columns(2)
                ->schema([
                    DateTimePicker::make('fecha_inicio')
                        ->label('Mostrar desde')
                        ->native(false)
                        ->seconds(false),

                    DateTimePicker::make('fecha_fin')
                        ->label('Mostrar hasta')
                        ->native(false)
                        ->seconds(false)
                        ->after('fecha_inicio'),
                ]),
        ]);
    }
}

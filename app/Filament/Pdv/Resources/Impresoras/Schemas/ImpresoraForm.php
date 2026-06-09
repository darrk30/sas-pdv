<?php

namespace App\Filament\Pdv\Resources\Impresoras\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ImpresoraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalles de la Impresora')
                    ->description('Define el nombre y la impresora que recibirá los pedidos de esta área.')
                    ->columns(2) // 🌟 ESTO ES LO QUE NECESITABAS: Divide en 2 columnas
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre de la Impresora')
                            ->placeholder('Ej. Impresora Térmica de Caja')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('descripcion')
                            ->label('Descripción / Ubicación')
                            ->placeholder('Ej. Ubicada en la barra principal')
                            ->rows(3)
                            ->maxLength(65535),

                        Toggle::make('estado')
                            ->label('Impresora Activa')
                            ->default(true)
                            ->helperText('Si se desactiva, no aparecerá en las opciones de impresión.'),
                    ])->columnSpanFull(),
            ]);
    }
}

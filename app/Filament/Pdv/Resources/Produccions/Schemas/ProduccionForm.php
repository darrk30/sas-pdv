<?php

namespace App\Filament\Pdv\Resources\Produccions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProduccionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configuración del Área')
                    ->description('Define el nombre y la impresora que recibirá los pedidos de esta área.')
                    ->columns(2) // 🌟 ESTO ES LO QUE NECESITABAS: Divide en 2 columnas
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre del Área')
                            ->placeholder('Ej. Cocina Caliente, Barra')
                            ->required()
                            ->maxLength(255),

                        Select::make('impresora_id')
                            ->label('Impresora Asignada')
                            ->relationship('impresora', 'nombre')
                            ->placeholder('Seleccione una impresora')
                            ->searchable()
                            ->preload(),

                        Toggle::make('estado')
                            ->label('Área Activa')
                            ->default(true),
                    ])->columnSpanFull(),
            ]);
    }
}

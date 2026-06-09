<?php

namespace App\Filament\Pdv\Resources\Dimensions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DimensionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la Dimensión')
                    ->description('Ejemplo: Masa, Volumen, Longitud')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre de la Magnitud')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Toggle::make('estado')
                            ->label('Activo')
                            ->default(true),
                    ])->columnSpanFull(),
            ]);
    }
}

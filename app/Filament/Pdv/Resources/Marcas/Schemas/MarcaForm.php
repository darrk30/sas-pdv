<?php

namespace App\Filament\Pdv\Resources\Marcas\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MarcaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la Marca')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nombre')
                            ->required()
                            ->maxLength(255),

                        FileUpload::make('logo')
                            ->label('Logo de la marca')
                            ->image()
                            ->directory('marcas') // Guarda en storage/app/public/marcas
                            ->columnSpanFull(),

                        Toggle::make('estado')
                            ->default(true),
                    ])->columnSpanFull(),
            ]);
    }
}

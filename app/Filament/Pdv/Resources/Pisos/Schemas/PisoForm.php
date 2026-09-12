<?php

namespace App\Filament\Pdv\Resources\Pisos\Schemas;

use App\Models\Impresora;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PisoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Información del Piso')
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([

                        TextInput::make('nombre')
                            ->label('Nombre del piso')
                            ->required()
                            ->placeholder('Ej: Planta Baja, Terraza, Salón VIP')
                            ->maxLength(100),

                        TextInput::make('orden')
                            ->label('Orden de visualización')
                            ->numeric()
                            ->default(0)
                            ->helperText('Menor número = aparece primero'),

                        Select::make('impresora_id')
                            ->label('Impresora de pre-cuenta')
                            ->placeholder('Sin impresora asignada')
                            ->helperText('La impresora donde se imprimirá la pre-cuenta de las mesas de este piso.')
                            ->options(fn (): array =>
                                Impresora::where('empresa_id', Filament::getTenant()->id)
                                    ->where('estado', true)
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id')
                                    ->toArray()
                            )
                            ->searchable()
                            ->nullable(),

                        Toggle::make('estado')
                            ->label('Piso activo')
                            ->default(true)
                            ->helperText('Los pisos inactivos no aparecen en el mapa de mesas.'),
                    ]),
                ])->columnSpanFull(),
        ]);
    }
}

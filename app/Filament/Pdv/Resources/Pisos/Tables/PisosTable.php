<?php

namespace App\Filament\Pdv\Resources\Pisos\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PisosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('orden')
                    ->label('#')
                    ->sortable()
                    ->width(50),

                TextColumn::make('nombre')
                    ->label('Piso')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('impresora.nombre')
                    ->label('Impresora pre-cuenta')
                    ->icon('heroicon-m-printer')
                    ->placeholder('Sin impresora'),

                TextColumn::make('mesas_count')
                    ->label('Mesas')
                    ->counts('mesas')
                    ->badge()
                    ->color('gray'),

                ToggleColumn::make('estado')
                    ->label('Activo')
                    ->disabled(fn () => ! auth()->user()?->can('mesas.editar')),
            ])
            ->defaultSort('orden')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}

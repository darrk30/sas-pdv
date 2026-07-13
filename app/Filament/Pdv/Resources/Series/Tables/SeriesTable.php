<?php

namespace App\Filament\Pdv\Resources\Series\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class SeriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                TextColumn::make('serie')
                    ->label('Serie')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('numero')
                    ->label('Número actual')
                    ->sortable(),

                ToggleColumn::make('estado')
                    ->label('Activa')
                    ->disabled(fn() => ! auth()->user()?->can('series.editar')),
            ])
            ->defaultSort('tipo')
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

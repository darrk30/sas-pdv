<?php

namespace App\Filament\Pdv\Resources\Produccions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ProduccionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Área')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('impresora.nombre')
                    ->label('Impresora')
                    ->icon('heroicon-m-printer')
                    ->placeholder('Sin impresora'),

                ToggleColumn::make('estado')
                    ->label('Estado'),
            ])
            ->filters([
                //
            ])
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

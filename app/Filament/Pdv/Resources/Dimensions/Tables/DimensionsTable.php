<?php

namespace App\Filament\Pdv\Resources\Dimensions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class DimensionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Magnitud')
                    ->searchable()
                    ->sortable(),
                ToggleColumn::make('estado')
                    ->label('Estado'),
                TextColumn::make('unidades_medida_count')
                    ->label('Unidades')
                    ->counts('unidadesMedida') // Muestra cuántas unidades tiene creadas
                    ->badge(),
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

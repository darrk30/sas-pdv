<?php

namespace App\Filament\Pdv\Resources\MetodosEnvio\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MetodosEnvioTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('costo')
                    ->label('Costo')
                    ->money('PEN')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'activo'   => 'success',
                        'inactivo' => 'gray',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'activo'   => 'Activo',
                        'inactivo' => 'Inactivo',
                        default    => $state,
                    }),

                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])

            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(['activo' => 'Activo', 'inactivo' => 'Inactivo']),
            ])

            ->recordActions([
                EditAction::make(),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])

            ->defaultSort('nombre')
            ->striped();
    }
}

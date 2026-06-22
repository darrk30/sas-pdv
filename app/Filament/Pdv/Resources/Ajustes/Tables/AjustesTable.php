<?php

namespace App\Filament\Pdv\Resources\Ajustes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AjustesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->width('60px'),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'entrada' => 'success',
                        'salida'  => 'danger',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'entrada' => 'Entrada',
                        'salida'  => 'Salida',
                        default   => $state,
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'entrada' => 'heroicon-o-arrow-down-tray',
                        'salida'  => 'heroicon-o-arrow-up-tray',
                        default   => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),

                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->limit(50)
                    ->tooltip(fn(string $state): string => $state)
                    ->searchable(),

                TextColumn::make('responsable.name')
                    ->label('Responsable')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('detalles_count')
                    ->label('Ítems')
                    ->counts('detalles')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('valor_total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'borrador' => 'warning',
                        'aplicado' => 'success',
                        'anulado'  => 'danger',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'borrador' => 'Borrador',
                        'aplicado' => 'Aplicado',
                        'anulado'  => 'Anulado',
                        default    => $state,
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])

            ->filters([

                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'entrada' => 'Entrada',
                        'salida'  => 'Salida',
                    ]),

                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'borrador' => 'Borrador',
                        'aplicado' => 'Aplicado',
                        'anulado'  => 'Anulado',
                    ]),

            ])

            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->hidden(fn($record): bool => $record->estado === 'aplicado'),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn(): bool => false),
                ]),
            ])

            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

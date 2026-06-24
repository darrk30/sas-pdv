<?php

namespace App\Filament\Pdv\Resources\IngresoEgresos\Tables;

use App\Enums\CategoriaEgreso;
use App\Enums\TipoMovimiento;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IngresoEgresosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha_hora')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                TextColumn::make('categoria')
                    ->label('Categoría')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('entregado_a')
                    ->label('Persona')
                    ->placeholder('—')
                    ->formatStateUsing(fn($state, $record) => $state
                        ?? $record->receptor?->name
                        ?? '—')
                    ->searchable(),

                TextColumn::make('monto')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable()
                    ->color(fn($record) => $record->tipo === TipoMovimiento::Ingreso ? 'success' : 'danger'),

                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->limit(40)
                    ->tooltip(fn($state) => $state)
                    ->toggleable(),

                TextColumn::make('registradoPor.name')
                    ->label('Registrado por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('fecha_hora', 'desc')
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(TipoMovimiento::class),

                SelectFilter::make('categoria')
                    ->label('Categoría')
                    ->options(CategoriaEgreso::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

<?php

namespace App\Filament\Pdv\Resources\Promociones\Tables;

use App\Enums\EstadoPromocion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PromocionesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query
                ->where('estado', '!=', EstadoPromocion::Archivado->value)
                ->orderByRaw("CASE WHEN estado = ? THEN 1 ELSE 0 END", [EstadoPromocion::Inactivo->value])
                ->orderBy('nombre')
            )
            ->columns([
                ImageColumn::make('imagen')
                    ->label('')
                    ->circular()
                    ->size(40),

                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->description(fn($record) => $record->descripcion),

                TextColumn::make('precio')
                    ->label('Precio')
                    ->money('PEN')
                    ->sortable(),

                TextColumn::make('detalles_count')
                    ->label('Productos')
                    ->counts('detalles')
                    ->badge()
                    ->color('info'),

                TextColumn::make('codigo_promo')
                    ->label('Código')
                    ->placeholder('—')
                    ->badge()
                    ->color('warning'),

                TextColumn::make('usos_actuales')
                    ->label('Usos')
                    ->formatStateUsing(fn($state, $record) => $record->limite_usos
                        ? "{$state} / {$record->limite_usos}"
                        : "{$state} / ∞")
                    ->sortable(),

                TextColumn::make('fecha_inicio')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('fecha_fin')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        EstadoPromocion::Activo->value   => EstadoPromocion::Activo->getLabel(),
                        EstadoPromocion::Inactivo->value => EstadoPromocion::Inactivo->getLabel(),
                    ]),
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

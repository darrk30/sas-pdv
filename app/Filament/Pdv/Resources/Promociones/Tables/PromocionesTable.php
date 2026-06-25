<?php

namespace App\Filament\Pdv\Resources\Promociones\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PromocionesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('imagen')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn() => null)
                    ->size(40),

                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('precio')
                    ->label('Precio (S/)')
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

                IconColumn::make('estado')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('estado')
                    ->label('Activa'),
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

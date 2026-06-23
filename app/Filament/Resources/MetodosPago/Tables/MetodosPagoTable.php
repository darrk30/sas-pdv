<?php

namespace App\Filament\Resources\MetodosPago\Tables;

use App\Enums\CondicionPago;
use App\Enums\EstadoGeneral;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MetodosPagoTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                ImageColumn::make('imagen')
                    ->label('Logo')
                    ->circular()
                    ->defaultImageUrl(asset('images/placeholder.png'))
                    ->size(40),

                TextColumn::make('empresa.nombre')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('condicion_pago')
                    ->label('Condición')
                    ->badge()
                    ->color(fn(CondicionPago $state): string|array|null => $state->getColor())
                    ->formatStateUsing(fn(CondicionPago $state): string => $state->getLabel()),

                IconColumn::make('requiere_referencia')
                    ->label('Req. referencia')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(EstadoGeneral $state): string => $state->getColor())
                    ->formatStateUsing(fn(EstadoGeneral $state): string => $state->getLabel()),

            ])

            ->filters([

                SelectFilter::make('empresa')
                    ->label('Empresa')
                    ->relationship('empresa', 'nombre'),

                SelectFilter::make('condicion_pago')
                    ->label('Condición de pago')
                    ->options(CondicionPago::class),

                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(EstadoGeneral::class),

            ])

            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])

            ->defaultSort('nombre')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

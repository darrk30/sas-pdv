<?php

namespace App\Filament\Pdv\Resources\Compras\Tables;

use App\Enums\EstadoDespacho;
use App\Enums\EstadoPago;
use App\Enums\TipoComprobante;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ComprasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tipo_comprobante')
                    ->label('Comprobante')
                    ->badge()
                    ->color(fn(TipoComprobante $state): string|array|null => $state->getColor())
                    ->formatStateUsing(fn(TipoComprobante $state): string => $state->getLabel()),

                TextColumn::make('fecha_compra')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('estado_despacho')
                    ->label('Despacho')
                    ->badge()
                    ->color(fn(EstadoDespacho $state): string|array|null => $state->getColor())
                    ->formatStateUsing(fn(EstadoDespacho $state): string => $state->getLabel()),

                TextColumn::make('estado_pago')
                    ->label('Pago')
                    ->badge()
                    ->color(fn(EstadoPago $state): string|array|null => $state->getColor())
                    ->formatStateUsing(fn(EstadoPago $state): string => $state->getLabel()),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])

            ->filters([

                SelectFilter::make('tipo_comprobante')
                    ->label('Tipo de comprobante')
                    ->options(TipoComprobante::class),

                SelectFilter::make('estado_despacho')
                    ->label('Estado de despacho')
                    ->options(EstadoDespacho::class),

                SelectFilter::make('estado_pago')
                    ->label('Estado de pago')
                    ->options(EstadoPago::class),

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

            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

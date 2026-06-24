<?php

namespace App\Filament\Pdv\Resources\SesionCajas\Tables;

use App\Enums\EstadoSesion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SesionCajasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('caja.nombre')
                    ->label('Caja')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('cajero.name')
                    ->label('Cajero')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('fecha_apertura')
                    ->label('Apertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('fecha_cierre')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),

                TextColumn::make('total_sistema')
                    ->label('Total sistema')
                    ->money('PEN')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('total_cajero')
                    ->label('Total cajero')
                    ->money('PEN')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('diferencia_total')
                    ->label('Diferencia')
                    ->money('PEN')
                    ->placeholder('—')
                    ->color(fn($state) => match (true) {
                        $state === null  => 'gray',
                        $state < 0       => 'danger',
                        $state > 0       => 'warning',
                        default          => 'success',
                    })
                    ->toggleable(),
            ])
            ->defaultSort('fecha_apertura', 'desc')
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(EstadoSesion::class),
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

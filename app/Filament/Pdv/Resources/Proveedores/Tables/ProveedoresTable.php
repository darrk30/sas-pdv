<?php

namespace App\Filament\Pdv\Resources\Proveedores\Tables;

use App\Enums\EstadoGeneral;
use App\Enums\TipoDocumento;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProveedoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('nombre')
                    ->label('Nombre / Razón social')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('tipo_documento')
                    ->label('Tipo doc.')
                    ->badge()
                    ->formatStateUsing(fn(TipoDocumento $state): string => $state->getLabel())
                    ->color('gray'),

                TextColumn::make('numero_documento')
                    ->label('N° Documento')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('correo')
                    ->label('Correo')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('departamento')
                    ->label('Departamento')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(EstadoGeneral $state): string => $state->getColor())
                    ->formatStateUsing(fn(EstadoGeneral $state): string => $state->getLabel()),

            ])

            ->filters([

                SelectFilter::make('tipo_documento')
                    ->label('Tipo de documento')
                    ->options(TipoDocumento::class),

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

<?php

namespace App\Filament\Pdv\Resources\Productos\Tables;

use App\Enums\EstadoGeneral;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class ProductosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('codigo_interno')
                    ->label('Cód. Interno')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('categoria.nombre')
                    ->label('Categoría')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('marca.nombre')
                    ->label('Marca')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('unidadMedida.nombre')
                    ->label('Unidad')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('precio_costo')
                    ->label('Costo')
                    ->money('PEN')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('precio_venta')
                    ->label('Precio Venta')
                    ->money('PEN')
                    ->sortable(),

                TextColumn::make('produccion.nombre')
                    ->label('Área Prod.')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Toggle para Control de Stock
                ToggleColumn::make('control_de_stock')
                    ->label('¿Controla Stock?')
                    ->onColor('success')
                    ->offColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('etiqueta')
                    ->label('Etiqueta')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(EstadoGeneral $state): string => match ($state) {
                        EstadoGeneral::Activo    => 'success', // Verde
                        EstadoGeneral::Inactivo  => 'warning', // Amarillo
                        EstadoGeneral::Archivado => 'danger',  // Rojo
                    })
                    ->sortable(),
            ])
            ->filters([
                Filter::make('solo_activos')
                    ->query(fn($query) => $query->where('estado', 'activo')),
            ])
            ->recordActions([

                ActionGroup::make([
                    EditAction::make(),
                    Action::make('activar')
                        ->label('Activar')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->authorize(fn() => auth()->user()?->can('productos.activar'))
                        ->hidden(fn($record) => $record->estado === EstadoGeneral::Activo)
                        ->action(fn($record) => $record->update(['estado' => EstadoGeneral::Activo])),

                    Action::make('desactivar')
                        ->label('Desactivar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->authorize(fn() => auth()->user()?->can('productos.activar'))
                        ->hidden(fn($record) => $record->estado === EstadoGeneral::Inactivo)
                        ->action(fn($record) => $record->update(['estado' => EstadoGeneral::Inactivo])),

                    Action::make('archivar')
                        ->label('Archivar')
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->authorize(fn() => auth()->user()?->can('productos.activar'))
                        ->hidden(fn($record) => $record->estado === EstadoGeneral::Archivado)
                        ->action(fn($record) => $record->update(['estado' => EstadoGeneral::Archivado])),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort(function ($query) {
                // Ordenar por estado para que los activos salgan primero
                // Asumiendo que 'activo' debe ir arriba, usamos una lógica de campo calculado o orden simple
                $query->orderByRaw("FIELD(estado, 'activo', 'inactivo', 'archivado') ASC");
                $query->orderBy('created_at', 'desc');
            });
    }
}

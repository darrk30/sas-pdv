<?php

namespace App\Filament\Pdv\Resources\Marcas\Tables;

use App\Models\Producto;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class MarcasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->label('Logo')
                    ->circular()
                    ->imageSize(40),
                TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),
                ToggleColumn::make('estado')
                    ->disabled(fn() => ! auth()->user()?->can('marcas.editar')),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('eliminar')
                    ->label('Eliminar')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation(fn($record) => Producto::where('marca_id', $record->id)->exists())
                    ->modalHeading(fn($record) => "Eliminar marca: {$record->nombre}")
                    ->modalDescription(fn($record) => '⚠️ Esta marca está asignada a ' . Producto::where('marca_id', $record->id)->count() . ' producto(s). Al eliminarla, esos productos quedarán sin marca. Esta acción no se puede deshacer.')
                    ->modalIconColor('warning')
                    ->action(fn($record) => $record->delete())
                    ->visible(fn() => auth()->user()?->can('marcas.eliminar') ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}

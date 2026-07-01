<?php

namespace App\Filament\Pdv\Resources\Roles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre del rol')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('permissions_count')
                    ->label('Permisos')
                    ->counts('permissions')
                    ->badge()
                    ->color(fn(int $state): string => $state > 0 ? 'success' : 'gray')
                    ->formatStateUsing(fn(int $state): string => $state > 0 ? "$state permisos" : 'Sin permisos'),

                TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->counts('users')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn(int $state): string => "$state usuario" . ($state !== 1 ? 's' : '')),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-shield-check')
            ->emptyStateHeading('Sin roles creados')
            ->emptyStateDescription('Crea tu primer rol para asignárselo a los usuarios.');
    }
}

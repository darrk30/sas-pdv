<?php

namespace App\Filament\Resources\Anuncios;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class AnuncioTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'info'        => 'info',
                        'advertencia' => 'warning',
                        'peligro'     => 'danger',
                        'exito'       => 'success',
                        default       => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'info'        => 'ℹ️ Información',
                        'advertencia' => '⚠️ Advertencia',
                        'peligro'     => '🚨 Peligro',
                        'exito'       => '✅ Éxito',
                        default       => $state,
                    }),

                TextColumn::make('fecha_inicio')
                    ->label('Desde')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Inmediato'),

                TextColumn::make('fecha_fin')
                    ->label('Hasta')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Sin límite'),

                ToggleColumn::make('activo')
                    ->label('Activo')
                    ->onColor('success'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

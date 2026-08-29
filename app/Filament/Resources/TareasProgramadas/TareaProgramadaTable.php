<?php

namespace App\Filament\Resources\TareasProgramadas;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class TareaProgramadaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('comando')
                    ->label('Comando')
                    ->fontFamily('mono')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('hora')
                    ->label('Hora')
                    ->badge()
                    ->color('info'),

                ToggleColumn::make('activo')
                    ->label('Activa')
                    ->onColor('success'),

                TextColumn::make('ultima_ejecucion')
                    ->label('Última ejecución')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Nunca')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('hora');
    }
}

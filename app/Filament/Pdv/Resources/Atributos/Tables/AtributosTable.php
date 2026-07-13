<?php

namespace App\Filament\Pdv\Resources\Atributos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AtributosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre del Atributo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // Muestra si es Texto o Color con un diseño de etiqueta
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge() 
                    ->color(fn (string $state): string => match ($state) {
                        'color' => 'warning', // Naranja para Color
                        'texto' => 'info',    // Azul para Texto
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)) // Pone la primera letra en mayúscula
                    ->sortable(),

                // 🌟 MAGIA: Muestra los nombres de la tabla relacionada (Valor) como pequeñas etiquetas
                TextColumn::make('valores.nombre')
                    ->label('Opciones Disponibles')
                    ->badge()
                    ->color('success')
                    ->limitList(5) // Si hay más de 5 valores, los oculta y pone un "+X"
                    ->searchable(),

                ToggleColumn::make('estado')
                    ->label('Estado')
                    ->sortable()
                    ->disabled(fn() => ! auth()->user()?->can('atributos.editar')),

                TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('estado')
                    ->label('Estado')
                    ->boolean()
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos')
                    ->native(false),

                // Un filtro extra para buscar rápidamente por tipo
                SelectFilter::make('tipo')
                    ->label('Tipo de Atributo')
                    ->options([
                        'texto' => 'Texto',
                        'color' => 'Color',
                    ])
                    ->native(false),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

<?php

namespace App\Filament\Pdv\Resources\Categorias\Tables;

use App\Models\Producto;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;

class CategoriasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('imagen_url')
                    ->label('Imagen')
                    ->circular()
                    ->defaultImageUrl(url('https://images.icon-icons.com/2406/PNG/512/tags_categories_icon_145927.png')),

                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // 🌟 Cambiado a ToggleColumn para editar directamente en la tabla
                ToggleColumn::make('estado')
                    ->label('Estado')
                    ->sortable()
                    ->disabled(fn() => ! auth()->user()?->can('categorias.editar')),

                // Opcional: Mostrar el número de orden (puedes quitarlo si prefieres que sea invisible)
                TextColumn::make('orden')
                    ->label('Orden')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas')
                    ->native(false),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('eliminar')
                    ->label('Eliminar')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation(fn($record) => Producto::where('categoria_id', $record->id)->exists())
                    ->modalHeading(fn($record) => "Eliminar categoría: {$record->nombre}")
                    ->modalDescription(fn($record) => '⚠️ Esta categoría está asignada a ' . Producto::where('categoria_id', $record->id)->count() . ' producto(s). Al eliminarla, esos productos quedarán sin categoría. Esta acción no se puede deshacer.')
                    ->modalIconColor('warning')
                    ->action(fn($record) => $record->delete())
                    ->visible(fn() => auth()->user()?->can('categorias.eliminar') ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('orden')
            ->defaultSort(fn ($query) => $query->orderBy('estado', 'desc')->orderBy('orden', 'asc'));
    }
}

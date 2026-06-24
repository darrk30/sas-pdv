<?php

namespace App\Filament\Pdv\Pages;

use App\Models\AjusteDetalle;
use App\Models\Inventario;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class GestionInventario extends Page implements HasTable
{
    use InteractsWithTable;

    // Estas propiedades de navegación SÍ son estáticas en Filament
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Inventario';

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';
    
    // ELIMINAMOS 'static' de aquí para evitar el Error Fatal de PHP
    protected string $view = 'filament.pdv.pages.gestion-inventario';
    
    // Usamos $heading (no estático) en lugar de $title para evitar posibles choques similares
    protected ?string $heading = 'Inventario Activo';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Inventario::query()
                    ->where('estado_almacen', 'activo')
                    ->whereHas('producto', fn(Builder $q) => $q->where('estado', '!=', 'archivado'))
                    ->with([
                        'producto',
                        'variante.valores.valor',
                        'variante.producto',
                    ])
            )
            ->columns([
                TextColumn::make('producto.nombre')
                    ->label('Producto')
                    ->formatStateUsing(function (string $state, Inventario $record): string {
                        if ($record->variante_id && $record->variante) {
                            return AjusteDetalle::generarNombre(null, $record->variante);
                        }
                        return $state;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            // Busca por nombre del producto (cubre simples y variantes)
                            $q->whereHas('producto', fn(Builder $pq) =>
                                $pq->where('nombre', 'like', "%{$search}%")
                            )
                            // Busca por valor de atributo: Rojo, S, M, etc.
                            ->orWhereHas('variante.valores.valor', fn(Builder $vq) =>
                                $vq->where('nombre', 'like', "%{$search}%")
                            );
                        });
                    })
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('stock_real')
                    ->label('Stock Actual')
                    ->numeric()
                    ->sortable()
                    ->alignRight(),

                TextColumn::make('stock_minimo')
                    ->label('Min.')
                    ->numeric()
                    ->alignRight()
                    ->color('gray'),

                TextColumn::make('estado_inventario')
                    ->label('Estado')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('estado_inventario')
                    ->label('Estado de stock')
                    ->options([
                        'agotado'      => 'Agotado',
                        'por_agotarse' => 'Por agotarse',
                        'disponible'   => 'Disponible',
                    ])
                    ->multiple()
                    ->placeholder('Todos los estados'),
            ])
            ->recordActions([
                // Acciones (se pueden añadir después)
            ]);
    }
}
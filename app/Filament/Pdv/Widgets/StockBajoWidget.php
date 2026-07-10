<?php

namespace App\Filament\Pdv\Widgets;

use App\Models\AjusteDetalle;
use App\Models\Inventario;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class StockBajoWidget extends BaseWidget
{
    protected static ?string $heading = 'Productos por agotarse / agotados';
    protected static ?int $sort = 8;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Inventario::query()
                    ->with(['producto', 'variante.valores.valor'])
                    ->where('empresa_id', Filament::getTenant()->id)
                    ->where(function ($q) {
                        $q->where('stock_reserva', '<=', 0)
                          ->orWhere(function ($q2) {
                              $q2->where('stock_minimo', '>', 0)
                                 ->whereColumn('stock_reserva', '<=', 'stock_minimo');
                          });
                    })
            )
            ->columns([
                Tables\Columns\TextColumn::make('producto.nombre')
                    ->label('Producto')
                    ->formatStateUsing(function (string $state, Inventario $record): string {
                        if ($record->variante_id && $record->variante) {
                            return AjusteDetalle::generarNombre(null, $record->variante);
                        }
                        return $state;
                    })
                    ->searchable()
                    ->sortable()
                    ->limit(60),

                Tables\Columns\TextColumn::make('stock_reserva')
                    ->label('Stock actual')
                    ->numeric(2)
                    ->alignEnd()
                    ->sortable()
                    ->color(fn($record) => (float) $record->stock_reserva <= 0 ? 'danger' : 'warning'),

                Tables\Columns\TextColumn::make('stock_minimo')
                    ->label('Stock mínimo')
                    ->numeric(2)
                    ->alignEnd()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('nivel')
                    ->label('Nivel')
                    ->getStateUsing(fn($record) => (float) $record->stock_reserva <= 0 ? 'Agotado' : 'Bajo')
                    ->badge()
                    ->color(fn($state) => $state === 'Agotado' ? 'danger' : 'warning'),
            ])
            ->defaultSort('stock_reserva', 'asc')
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10, 25, 50])
            ->striped();
    }
}

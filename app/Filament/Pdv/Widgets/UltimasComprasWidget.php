<?php

namespace App\Filament\Pdv\Widgets;

use App\Models\Compra;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UltimasComprasWidget extends BaseWidget
{
    protected static ?string $heading = 'Últimas compras';
    protected static ?int $sort = 7;
    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Compra::query()
                    ->with(['proveedor'])
                    ->where('empresa_id', Filament::getTenant()->id)
                    ->latest()
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->placeholder('—')
                    ->limit(22),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'confirmado' => 'Confirmado',
                        'anulado'    => 'Anulado',
                        default      => 'Borrador',
                    })
                    ->color(fn($state) => match($state) {
                        'confirmado' => 'success',
                        'anulado'    => 'danger',
                        default      => 'gray',
                    }),
            ])
            ->paginated(false)
            ->striped();
    }
}

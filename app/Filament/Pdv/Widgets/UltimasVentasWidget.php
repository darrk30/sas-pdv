<?php

namespace App\Filament\Pdv\Widgets;

use App\Models\Venta;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UltimasVentasWidget extends BaseWidget
{
    protected static ?string $heading = 'Últimas ventas';
    protected static ?int $sort = 6;
    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Venta::query()
                    ->with(['cliente', 'vendedor', 'serie'])
                    ->where('empresa_id', Filament::getTenant()->id)
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Hora')
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('comprobante')
                    ->label('Comprobante')
                    ->getStateUsing(fn(Venta $record): string =>
                        $record->serie
                            ? "{$record->serie->serie}-{$record->correlativo}"
                            : "—"
                    ),

                Tables\Columns\TextColumn::make('cliente.nombre')
                    ->label('Cliente')
                    ->placeholder('Sin cliente')
                    ->limit(25),

                Tables\Columns\TextColumn::make('vendedor.name')
                    ->label('Vendedor')
                    ->placeholder('—')
                    ->limit(20),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->alignEnd()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge(),
            ])
            ->paginated(false)
            ->striped();
    }
}

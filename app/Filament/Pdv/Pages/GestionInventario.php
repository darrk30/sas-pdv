<?php

namespace App\Filament\Pdv\Pages;

use App\Models\AjusteDetalle;
use App\Models\Inventario;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class GestionInventario extends Page implements HasTable
{
    use InteractsWithTable;

    // Estas propiedades de navegación SÍ son estáticas en Filament
    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArchiveBox;
    protected static ?string $navigationLabel = 'Control de Stock';
    
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
                    ->searchable()
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
                // Filtros (se pueden añadir después)
            ])
            ->recordActions([
                // Acciones (se pueden añadir después)
            ]);
    }
}
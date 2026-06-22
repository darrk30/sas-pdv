<?php

namespace App\Filament\Pdv\Pages;

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
                    ->with(['producto', 'variante']) 
            )
            ->columns([
                TextColumn::make('producto.nombre')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('variante_id')
                    ->label('Tipo / Variante')
                    ->formatStateUsing(function ($record) {
                        if (!$record->variante_id) {
                            return 'Producto Simple';
                        }
                        
                        // Si tu modelo Variante tiene la columna 'nombre', 
                        // puedes cambiar esto a: return $record->variante->nombre;
                        return 'Variante #' . $record->variante_id;
                    })
                    ->badge()
                    ->color(fn ($record) => $record->variante_id ? 'info' : 'gray'),

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
<?php

namespace App\Filament\Pdv\Resources\Cupones\Tables;

use App\Models\Cupon;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Livewire\Component;

class CuponesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Email address copied')
                    ->copyMessageDuration(1500),

                TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(),

                TextColumn::make('tipo_descuento')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state === 'porcentaje' ? 'Porcentaje' : 'Monto fijo')
                    ->color(fn($state) => $state === 'porcentaje' ? 'info' : 'warning'),

                TextColumn::make('valor')
                    ->label('Descuento')
                    ->formatStateUsing(
                        fn($state, Cupon $r) =>
                        $r->tipo_descuento === 'porcentaje'
                            ? number_format($state, 0) . '%'
                            : 'S/ ' . number_format($state, 2)
                    )
                    ->alignRight(),

                TextColumn::make('condicion')
                    ->label('Condición')
                    ->state(fn(Cupon $r) => collect([
                        $r->cantidad_minima ? "≥ {$r->cantidad_minima} productos" : null,
                        $r->monto_minimo    ? 'S/ ' . number_format($r->monto_minimo, 2) . ' mín.' : null,
                    ])->filter()->implode(' · ') ?: '—')
                    ->color('gray'),

                TextColumn::make('usos_stock')
                    ->label('Usos')
                    ->state(
                        fn(Cupon $r) =>
                        $r->stock === null
                            ? "{$r->usos} / ∞"
                            : "{$r->usos} / {$r->stock}"
                    )
                    ->alignCenter()
                    ->color(
                        fn(Cupon $r) =>
                        $r->stock !== null && $r->usos >= $r->stock ? 'danger' : 'gray'
                    ),

                TextColumn::make('fecha_fin')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->placeholder('Sin expiración')
                    ->color(
                        fn(Cupon $r) =>
                        $r->fecha_fin && $r->fecha_fin->isPast() ? 'danger' : 'gray'
                    ),

                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean()
                    ->alignCenter(),

            ])
            ->filters([
                TernaryFilter::make('activo')->label('Estado'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

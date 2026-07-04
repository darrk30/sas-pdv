<?php

namespace App\Filament\Pdv\Resources\Ordenes\Tables;

use App\Enums\EstadoOrden;
use App\Models\Orden;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdenesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable(query: fn($query, $search) => $query->whereRaw(
                        "CONCAT('ORD-', LPAD(numero, 8, '0')) LIKE ?", ["%{$search}%"]
                    ))
                    ->sortable(query: fn($query, $direction) => $query->orderBy('numero', $direction))
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('cliente_nombre')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('fecha_orden')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('tipo_entrega')
                    ->label('Entrega')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'envio'  => 'info',
                        'retiro' => 'success',
                        default  => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'envio'  => 'Envío',
                        'retiro' => 'Retiro',
                        default  => $state,
                    }),

                TextColumn::make('metodoEnvio.nombre')
                    ->label('Método envío')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(EstadoOrden $state): string => $state->getColor())
                    ->formatStateUsing(fn(EstadoOrden $state): string => $state->getLabel()),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('PEN')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])

            ->filters([

                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(EstadoOrden::class),

                SelectFilter::make('tipo_entrega')
                    ->label('Tipo de entrega')
                    ->options([
                        'envio'  => 'Envío',
                        'retiro' => 'Retiro',
                    ]),

            ])

            ->recordActions([

                EditAction::make()
                    ->visible(fn(Orden $record): bool => ! $record->estaCancelada()),

                Action::make('cancelar')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('¿Cancelar esta orden?')
                    ->modalDescription('La orden será marcada como cancelada. Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Sí, cancelar')
                    ->authorize(fn() => auth()->user()?->can('ordenes.cancelar'))
                    ->visible(fn(Orden $record): bool => $record->estado === EstadoOrden::PendientePago)
                    ->action(function (Orden $record): void {
                        $record->restaurarStockReserva();
                        $record->update(['estado' => EstadoOrden::Cancelada]);

                        Notification::make()
                            ->warning()
                            ->title('Orden ' . $record->codigo . ' cancelada')
                            ->send();
                    }),


            ])


            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

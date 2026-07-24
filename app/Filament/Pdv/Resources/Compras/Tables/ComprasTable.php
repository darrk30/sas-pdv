<?php

namespace App\Filament\Pdv\Resources\Compras\Tables;

use App\Enums\EstadoDespacho;
use App\Enums\EstadoPago;
use App\Enums\TipoComprobante;
use App\Models\Compra;
use App\Services\InventarioCoreService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ComprasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('tipo_comprobante')
                    ->label('Comprobante')
                    ->badge()
                    ->color(fn(string $state): string => TipoComprobante::from($state)->getColor())
                    ->formatStateUsing(fn(string $state): string => TipoComprobante::from($state)->getLabel()),

                TextColumn::make('fecha_compra')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('estado_despacho')
                    ->label('Despacho')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'recibido'  => 'success',
                        'pendiente' => 'warning',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'recibido'  => 'Recibido',
                        'pendiente' => 'Pendiente',
                        default     => $state,
                    }),

                TextColumn::make('estado_pago')
                    ->label('Pago')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pagado'    => 'success',
                        'pendiente' => 'warning',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pagado'    => 'Pagado',
                        'pendiente' => 'Pendiente',
                        default     => $state,
                    }),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'borrador'    => 'gray',
                        'confirmado'  => 'success',
                        'anulado'     => 'danger',
                        default       => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'borrador'    => 'Borrador',
                        'confirmado'  => 'Confirmado',
                        'anulado'     => 'Anulado',
                        default       => $state,
                    }),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

            ])

            ->filters([

                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'borrador'   => 'Borrador',
                        'confirmado' => 'Confirmado',
                        'anulado'    => 'Anulado',
                    ]),

                SelectFilter::make('tipo_comprobante')
                    ->label('Tipo de comprobante')
                    ->options(TipoComprobante::class),

                SelectFilter::make('estado_despacho')
                    ->label('Estado de despacho')
                    ->options(EstadoDespacho::class),

                SelectFilter::make('estado_pago')
                    ->label('Estado de pago')
                    ->options(EstadoPago::class),

            ])

            ->recordActions([

                EditAction::make()
                    ->visible(fn(Compra $record): bool => ! $record->estaAnulada()),

                Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('¿Anular compra?')
                    ->modalDescription(fn(Compra $record): string => $record->estaRecibida()
                        ? 'La compra está recibida: se revertirá el stock ingresado. Esta acción no se puede deshacer.'
                        : 'La compra será marcada como anulada. Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Sí, anular')
                    ->authorize(fn() => auth()->user()?->can('compras.anular'))
                    ->visible(fn(Compra $record): bool => ! $record->estaAnulada())
                    ->action(function (Compra $record): void {
                        $eraRecibida = $record->estaRecibida();
                        if ($eraRecibida) {
                            app(InventarioCoreService::class)->revertirCompra($record);
                        }
                        $record->update(['estado' => 'anulado']);

                        Notification::make()
                            ->warning()
                            ->title('Compra ' . $record->codigo . ' anulada')
                            ->body($eraRecibida ? 'La compra fue anulada y el stock revertido.' : 'La compra fue anulada.')
                            ->send();
                    }),

                // DeleteAction::make()->visible(fn(Compra $record): bool => ! $record->estaAnulada()),

            ])

            ->toolbarActions([
                // BulkActionGroup::make([
                //     DeleteBulkAction::make()
                //         ->action(function (\Illuminate\Support\Collection $records): void {
                //             $eliminados = 0;
                //             $omitidos   = 0;

                //             foreach ($records as $compra) {
                //                 if (! $compra->estaAnulada()) {
                //                     $compra->delete();
                //                     $eliminados++;
                //                 } else {
                //                     $omitidos++;
                //                 }
                //             }

                //             if ($omitidos > 0) {
                //                 Notification::make()
                //                     ->warning()
                //                     ->title("{$omitidos} compra(s) no eliminada(s)")
                //                     ->body('No se pueden eliminar compras anuladas.')
                //                     ->send();
                //             }

                //             if ($eliminados > 0) {
                //                 Notification::make()
                //                     ->success()
                //                     ->title("{$eliminados} compra(s) eliminada(s)")
                //                     ->send();
                //             }
                //         }),
                // ]),
            ])

            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

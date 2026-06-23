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
                    ->color(fn(TipoComprobante $state): string|array|null => $state->getColor())
                    ->formatStateUsing(fn(TipoComprobante $state): string => $state->getLabel()),

                TextColumn::make('fecha_compra')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('estado_despacho')
                    ->label('Despacho')
                    ->badge()
                    ->color(fn(EstadoDespacho $state): string|array|null => $state->getColor())
                    ->formatStateUsing(fn(EstadoDespacho $state): string => $state->getLabel()),

                TextColumn::make('estado_pago')
                    ->label('Pago')
                    ->badge()
                    ->color(fn(EstadoPago $state): string|array|null => $state->getColor())
                    ->formatStateUsing(fn(EstadoPago $state): string => $state->getLabel()),

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

                // Confirmar: solo cuando recibida + pagada + borrador
                Action::make('confirmar')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('¿Confirmar compra?')
                    ->modalDescription('Al confirmar, la compra quedará bloqueada y no podrá ser editada.')
                    ->modalSubmitActionLabel('Sí, confirmar')
                    ->visible(fn(Compra $record): bool => $record->listaParaConfirmar())
                    ->action(function (Compra $record): void {
                        $record->update(['estado' => 'confirmado']);

                        Notification::make()
                            ->success()
                            ->title('Compra ' . $record->codigo . ' confirmada')
                            ->send();
                    }),

                // Editar: solo borradores
                EditAction::make()
                    ->visible(fn(Compra $record): bool => $record->esBorrador()),

                // Eliminar: solo borradores
                DeleteAction::make()
                    ->visible(fn(Compra $record): bool => $record->esBorrador()),

            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (\Illuminate\Support\Collection $records): void {
                            $eliminados = 0;
                            $omitidos   = 0;

                            foreach ($records as $compra) {
                                if ($compra->esBorrador()) {
                                    $compra->delete();
                                    $eliminados++;
                                } else {
                                    $omitidos++;
                                }
                            }

                            if ($omitidos > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title("{$omitidos} compra(s) no eliminada(s)")
                                    ->body('Solo se pueden eliminar compras en borrador.')
                                    ->send();
                            }

                            if ($eliminados > 0) {
                                Notification::make()
                                    ->success()
                                    ->title("{$eliminados} compra(s) eliminada(s)")
                                    ->send();
                            }
                        }),
                ]),
            ])

            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

<?php

namespace App\Filament\Pdv\Resources\Ajustes\Tables;

use App\Models\Ajuste;
use App\Services\InventarioCoreService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class AjustesTable
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

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'entrada' => 'success',
                        'salida'  => 'danger',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'entrada' => 'Entrada',
                        'salida'  => 'Salida',
                        default   => $state,
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'entrada' => 'heroicon-o-arrow-down-tray',
                        'salida'  => 'heroicon-o-arrow-up-tray',
                        default   => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),

                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->limit(50)
                    ->tooltip(fn(string $state): string => $state)
                    ->searchable(),

                TextColumn::make('responsable.name')
                    ->label('Responsable')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('detalles_count')
                    ->label('Ítems')
                    ->counts('detalles')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('valor_total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'borrador'   => 'warning',
                        'confirmado' => 'success',
                        'anulado'    => 'danger',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'borrador'   => 'Borrador',
                        'confirmado' => 'Confirmado',
                        'anulado'    => 'Anulado',
                        default      => $state,
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])

            ->filters([

                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'entrada' => 'Entrada',
                        'salida'  => 'Salida',
                    ]),

                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'borrador'   => 'Borrador',
                        'confirmado' => 'Confirmado',
                        'anulado'    => 'Anulado',
                    ]),

            ])

            ->recordActions([

                // Confirmar: solo borradores → aplica stock y bloquea el ajuste
                Action::make('confirmar')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('¿Confirmar ajuste?')
                    ->modalDescription('Al confirmar se aplicará el movimiento de stock. Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Sí, confirmar')
                    ->authorize(fn() => auth()->user()?->can('ajustes.confirmar'))
                    ->visible(fn(Ajuste $record): bool => $record->estado === 'borrador')
                    ->action(function (Ajuste $record): void {
                        app(InventarioCoreService::class)->aplicarAjuste($record);
                        $record->update(['estado' => 'confirmado']);

                        Notification::make()
                            ->success()
                            ->title('Ajuste ' . $record->codigo . ' confirmado')
                            ->body('El stock ha sido actualizado correctamente.')
                            ->send();
                    }),

                // Anular: solo confirmados → revierte stock y bloquea
                Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('¿Anular ajuste?')
                    ->modalDescription('Se revertirá el movimiento de stock aplicado. Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Sí, anular')
                    ->authorize(fn() => auth()->user()?->can('ajustes.anular'))
                    ->visible(fn(Ajuste $record): bool => $record->estado === 'confirmado')
                    ->action(function (Ajuste $record): void {
                        app(InventarioCoreService::class)->revertirAjuste($record);
                        $record->update(['estado' => 'anulado']);

                        Notification::make()
                            ->warning()
                            ->title('Ajuste ' . $record->codigo . ' anulado')
                            ->body('El movimiento de stock fue revertido.')
                            ->send();
                    }),

                // Editar: solo borradores
                EditAction::make()
                    ->visible(fn(Ajuste $record): bool => $record->estado === 'borrador'),

                // Eliminar: solo borradores (sin revertir stock, nunca fue aplicado)
                DeleteAction::make()
                    ->visible(fn(Ajuste $record): bool => $record->estado === 'borrador'),

                // Ver: confirmados y anulados (solo lectura)
                ViewAction::make()
                    ->visible(fn(Ajuste $record): bool => $record->estado !== 'borrador'),

            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    // Eliminar en lote: solo procesa los que están en borrador
                    DeleteBulkAction::make()
                        ->action(function (Collection $records): void {
                            $eliminados = 0;
                            $omitidos   = 0;

                            foreach ($records as $ajuste) {
                                if ($ajuste->estado === 'borrador') {
                                    $ajuste->delete();
                                    $eliminados++;
                                } else {
                                    $omitidos++;
                                }
                            }

                            if ($omitidos > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title("{$omitidos} ajuste(s) no eliminado(s)")
                                    ->body('Solo se pueden eliminar ajustes en borrador.')
                                    ->send();
                            }

                            if ($eliminados > 0) {
                                Notification::make()
                                    ->success()
                                    ->title("{$eliminados} ajuste(s) eliminado(s)")
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

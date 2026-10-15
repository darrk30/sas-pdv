<?php

namespace App\Filament\Pdv\Resources\Compras\Pages;

use App\Filament\Pdv\Resources\Compras\CompraResource;
use App\Models\Compra;
use App\Services\InventarioCoreService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCompra extends ViewRecord
{
    protected static string $resource = CompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn() => ! $this->record->estaAnulada()),

            Action::make('anular')
                ->label('Anular compra')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('¿Anular compra?')
                ->modalDescription(fn() => $this->record->estaRecibida()
                    ? 'La compra está recibida: se revertirá el stock ingresado. Esta acción no se puede deshacer.'
                    : 'La compra será marcada como anulada. Esta acción no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, anular')
                ->authorize(fn() => auth()->user()?->can('compras.anular'))
                ->visible(fn() => ! $this->record->estaAnulada())
                ->action(function (): void {
                    /** @var Compra $compra */
                    $compra = $this->record;
                    $eraRecibida = $compra->estaRecibida();

                    if ($eraRecibida) {
                        app(InventarioCoreService::class)->revertirCompra($compra);
                    }

                    $compra->update(['estado' => 'anulado']);

                    Notification::make()
                        ->warning()
                        ->title('Compra ' . $compra->codigo . ' anulada')
                        ->body($eraRecibida ? 'La compra fue anulada y el stock revertido.' : 'La compra fue anulada.')
                        ->send();

                    $this->refreshFormData(['estado', 'estado_despacho']);
                }),
        ];
    }
}

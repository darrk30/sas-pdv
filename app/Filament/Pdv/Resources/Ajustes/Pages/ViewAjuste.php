<?php

namespace App\Filament\Pdv\Resources\Ajustes\Pages;

use App\Filament\Pdv\Resources\Ajustes\AjusteResource;
use App\Services\InventarioCoreService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAjuste extends ViewRecord
{
    protected static string $resource = AjusteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('anular')
                ->label('Anular ajuste')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('¿Anular ajuste?')
                ->modalDescription('Se revertirá el movimiento de stock aplicado. Esta acción no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, anular')
                ->visible(fn() => $this->record->estado === 'confirmado')
                ->action(function (): void {
                    app(InventarioCoreService::class)->revertirAjuste($this->record);
                    $this->record->update(['estado' => 'anulado']);

                    Notification::make()
                        ->warning()
                        ->title('Ajuste ' . $this->record->codigo . ' anulado')
                        ->body('El movimiento de stock fue revertido.')
                        ->send();

                    $this->refreshFormData(['estado']);
                }),
        ];
    }
}

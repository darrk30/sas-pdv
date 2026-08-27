<?php

namespace App\Filament\Pdv\Resources\Gastos\Pages;

use App\Enums\EstadoGasto;
use App\Filament\Pdv\Resources\Gastos\GastoResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditGasto extends EditRecord
{
    protected static string $resource = GastoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('aprobar')
                ->label('Aprobar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('¿Aprobar este gasto?')
                ->modalDescription('El gasto quedará marcado como aprobado.')
                ->modalSubmitActionLabel('Sí, aprobar')
                ->visible(fn () => $this->getRecord()->estado === EstadoGasto::Pendiente
                    && (auth()->user()?->can('gastos.crear') ?? false))
                ->action(function () {
                    $this->getRecord()->update(['estado' => EstadoGasto::Aprobado]);

                    Notification::make()
                        ->title('Gasto aprobado')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Action::make('anular')
                ->label('Anular')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('¿Anular este gasto?')
                ->modalDescription('Esta acción no se puede deshacer. El gasto quedará marcado como anulado.')
                ->modalSubmitActionLabel('Sí, anular')
                ->visible(fn () => ! $this->getRecord()->estaAnulado()
                    && (auth()->user()?->can('gastos.anular') ?? false))
                ->action(function () {
                    $this->getRecord()->update(['estado' => EstadoGasto::Anulado]);

                    Notification::make()
                        ->title('Gasto anulado')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['empresa_id'], $data['user_id'], $data['serie'], $data['correlativo'], $data['estado']);
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

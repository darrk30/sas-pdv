<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\EmpresaResource;
use App\Models\Empresa;
use App\Services\FacturadorService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;

class EditEmpresa extends EditRecord
{
    protected static string $resource = EmpresaResource::class;

    #[On('cancelar-empresa-eliminar')]
    public function cancelarEliminar(): void
    {
        $this->unmountAction();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('eliminar')
                ->label('Eliminar empresa')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->modalHeading('Eliminar empresa')
                ->modalContent(fn () => view('livewire.admin.eliminar-empresa-wrapper', [
                    'empresaId' => $this->record->id,
                ]))
                ->modalFooterActions([])
                ->modalWidth('lg'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['modulos_activos'] = array_merge(
            Empresa::defaultModulos(),
            $data['modulos_activos'] ?? [],
        );

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Empresa $empresa */
        $empresa = $this->record->fresh(['facturacion']);

        if (! $empresa->facturacion) {
            return;
        }

        $resultado = app(FacturadorService::class)->sincronizarEmpresa($empresa);

        if (! $resultado->ok) {
            Notification::make()
                ->title('Empresa guardada')
                ->body('Datos guardados, pero el facturador no pudo sincronizarse: ' . $resultado->mensajeError())
                ->warning()
                ->send();
        }
    }
}
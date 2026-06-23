<?php

namespace App\Filament\Pdv\Resources\Compras\Pages;

use App\Filament\Pdv\Resources\Compras\CompraResource;
use App\Services\InventarioCoreService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Collection;

class EditCompra extends EditRecord
{
    protected static string $resource = CompraResource::class;

    private string $estadoDespachoAntes;
    private Collection $detallesAntes;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $record = $this->getRecord();

        // Capturar estado ANTES de que Filament sobreescriba el registro
        $this->estadoDespachoAntes = $record->estado_despacho ?? 'pendiente';

        // Capturar detalles actuales en BD por si necesitamos revertir stock
        $this->detallesAntes = $record->detalles()->with('unidad')->get();
    }

    protected function afterSave(): void
    {
        $estadoViejo = $this->estadoDespachoAntes;
        $estadoNuevo = $this->getRecord()->estado_despacho ?? 'pendiente';

        // Si el estado de despacho no cambió, no tocar el stock
        if ($estadoViejo === $estadoNuevo) {
            return;
        }

        $record  = $this->getRecord();
        $service = app(InventarioCoreService::class);

        if ($estadoNuevo === 'recibido') {
            // pendiente → recibido: aumentar stock con los detalles ya guardados
            $service->aplicarCompra($record);
        } else {
            // recibido → pendiente: revertir con los detalles que había antes del guardado
            $service->revertirDetalles($record->empresa_id, 'entrada', $this->detallesAntes);
        }
    }
}

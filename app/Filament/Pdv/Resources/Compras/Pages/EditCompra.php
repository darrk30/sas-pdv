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

        // Capturar estado y detalles ANTES de que Filament guarde cambios en BD
        $this->estadoDespachoAntes = $record->estado_despacho ?? 'pendiente';
        $this->detallesAntes       = $record->detalles()->with('unidad')->get();
    }

    protected function afterSave(): void
    {
        $record      = $this->getRecord();
        $estadoViejo = $this->estadoDespachoAntes;
        $estadoNuevo = $record->estado_despacho ?? 'pendiente';

        $service = app(InventarioCoreService::class);

        if ($estadoViejo === 'recibido' && $estadoNuevo === 'recibido') {
            // Sigue recibido: revertir con detalles viejos y aplicar con detalles nuevos
            $service->revertirDetalles($record->empresa_id, 'entrada', $this->detallesAntes);
            $service->aplicarCompra($record);
        } elseif ($estadoViejo !== 'recibido' && $estadoNuevo === 'recibido') {
            // Pasó a recibido: aplicar con los detalles ya guardados
            $service->aplicarCompra($record);
        } elseif ($estadoViejo === 'recibido' && $estadoNuevo !== 'recibido') {
            // Salió de recibido: revertir con los detalles que había antes del guardado
            $service->revertirDetalles($record->empresa_id, 'entrada', $this->detallesAntes);
        }
        // pendiente → pendiente: sin cambio de stock
    }
}

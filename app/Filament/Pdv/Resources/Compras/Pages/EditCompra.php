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

    // null = no action | 'aplicar' | 'revertir'
    private ?string $stockAction = null;
    private Collection $detallesAntes;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Se ejecuta ANTES de guardar el registro.
     * Recibe $data (valores nuevos del form) y $record aún tiene los valores viejos de BD.
     * Es el único punto donde tenemos acceso explícito a ambos.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->stockAction = null;

        $record      = $this->getRecord();
        $estadoViejo = $record->estado_despacho ?? 'pendiente';
        $estadoNuevo = $data['estado_despacho']  ?? 'pendiente';

        // Capturar detalles que están en BD ahora (antes de que el form los sobreescriba)
        $this->detallesAntes = $record->detalles()->with('unidad')->get();

        if ($estadoViejo !== $estadoNuevo) {
            $this->stockAction = ($estadoNuevo === 'recibido') ? 'aplicar' : 'revertir';
        }

        return $data;
    }

    /**
     * Se ejecuta DESPUÉS de guardar registro y relaciones.
     * Aquí los detalles nuevos ya están en BD, por lo que aplicarCompra los leerá correctamente.
     */
    protected function afterSave(): void
    {
        if ($this->stockAction === null) {
            return;
        }

        $record  = $this->getRecord();
        $service = app(InventarioCoreService::class);

        if ($this->stockAction === 'aplicar') {
            $service->aplicarCompra($record);
        } else {
            $service->revertirDetalles($record->empresa_id, 'entrada', $this->detallesAntes);
        }
    }
}

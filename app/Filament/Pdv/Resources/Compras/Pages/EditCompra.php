<?php

namespace App\Filament\Pdv\Resources\Compras\Pages;

use App\Filament\Pdv\Resources\Compras\CompraResource;
use App\Services\InventarioCoreService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditCompra extends EditRecord
{
    protected static string $resource = CompraResource::class;

    private ?string $stockAction    = null;
    private array   $detallesViejos = [];
    private array   $detallesNuevos = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Se ejecuta ANTES de que Filament guarde el registro y las relaciones.
     * Aquí capturamos el estado viejo (directo de BD) y el estado nuevo (del form $data),
     * así no dependemos del orden en que Filament llame a afterSave vs saveRelationships.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->stockAction    = null;
        $this->detallesViejos = [];
        $this->detallesNuevos = [];

        $record   = $this->getRecord();
        $compraId = $record->getKey();

        // Estado viejo: directo de BD, sin pasar por el modelo (que puede estar sincronizado con el form)
        $estadoViejo = DB::table('compras')
            ->where('id', $compraId)
            ->value('estado_despacho') ?? 'pendiente';

        // Estado nuevo: del form, normalizado por si llega como enum instance
        $raw         = $data['estado_despacho'] ?? 'pendiente';
        $estadoNuevo = $raw instanceof \BackedEnum ? $raw->value : (string) $raw;

        // Detalles viejos: directo de BD sin Eloquent (evita scopes y caché de relaciones)
        $viejos = DB::table('compra_detalles')
            ->where('compra_id', $compraId)
            ->get(['producto_id', 'variante_id', 'unidad_id', 'cantidad'])
            ->map(fn(\stdClass $row) => (array) $row)
            ->values()
            ->all();

        // Detalles nuevos: del form $data (disponibles antes del guardado, independiente del timing)
        $nuevos = array_values($data['detalles'] ?? []);

        if ($estadoViejo !== $estadoNuevo) {
            if ($estadoNuevo === 'recibido') {
                // pendiente → recibido: aplicar los nuevos detalles del form
                $this->stockAction    = 'aplicar';
                $this->detallesNuevos = $nuevos;
            } else {
                // recibido → pendiente: revertir los viejos que estaban en BD
                $this->stockAction    = 'revertir';
                $this->detallesViejos = $viejos;
            }
        } elseif ($estadoNuevo === 'recibido') {
            // recibido → recibido: revertir viejos y aplicar nuevos (sincroniza cambios de cantidad/unidad)
            $this->stockAction    = 'sincronizar';
            $this->detallesViejos = $viejos;
            $this->detallesNuevos = $nuevos;
        }
        // pendiente → pendiente: sin acción

        return $data;
    }

    /**
     * Se ejecuta después de que Filament guarda el registro.
     * Usamos los datos capturados en mutateFormDataBeforeSave (no consultamos la BD aquí)
     * para evitar dependencia del orden afterSave/saveRelationships.
     */
    protected function afterSave(): void
    {
        if ($this->stockAction === null) {
            return;
        }

        $record  = $this->getRecord();
        $service = app(InventarioCoreService::class);

        $viejos = collect($this->detallesViejos);
        $nuevos = collect($this->detallesNuevos);

        if ($this->stockAction === 'aplicar') {
            $service->aplicarDetalles($record->empresa_id, 'entrada', $nuevos);
        } elseif ($this->stockAction === 'revertir') {
            $service->revertirDetalles($record->empresa_id, 'entrada', $viejos);
        } else {
            // sincronizar: revertir con unidades/cantidades viejas, sumar con las nuevas
            $service->revertirDetalles($record->empresa_id, 'entrada', $viejos);
            $service->aplicarDetalles($record->empresa_id, 'entrada', $nuevos);
        }
    }
}

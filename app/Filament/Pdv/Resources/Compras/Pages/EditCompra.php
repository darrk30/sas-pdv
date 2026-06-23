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

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Captura el estado viejo y los detalles viejos ANTES de guardar.
     * Los detalles nuevos NO se capturan aquí — se leen de BD en afterSave,
     * donde Filament ya guardó las relaciones y el dato es confiable.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->stockAction    = null;
        $this->detallesViejos = [];

        $record   = $this->getRecord();
        $compraId = $record->getKey();

        $estadoViejo = DB::table('compras')
            ->where('id', $compraId)
            ->value('estado_despacho') ?? 'pendiente';

        $raw         = $data['estado_despacho'] ?? 'pendiente';
        $estadoNuevo = $raw instanceof \BackedEnum ? $raw->value : (string) $raw;

        if ($estadoViejo !== $estadoNuevo) {
            if ($estadoNuevo === 'recibido') {
                // pendiente → recibido: stock se aplicará en afterSave con los detalles ya guardados
                $this->stockAction = 'aplicar';
            } else {
                // recibido → pendiente: capturar viejos ahora antes de que se sobreescriban
                $this->stockAction    = 'revertir';
                $this->detallesViejos = $this->leerDetallesDeDB($compraId);
            }
        } elseif ($estadoNuevo === 'recibido') {
            // recibido → recibido: capturar viejos ahora; los nuevos se leerán en afterSave
            $this->stockAction    = 'sincronizar';
            $this->detallesViejos = $this->leerDetallesDeDB($compraId);
        }
        // pendiente → pendiente: sin acción

        return $data;
    }

    /**
     * Aplica los movimientos de stock usando los detalles ya persistidos en BD.
     * En este punto Filament ya guardó el registro principal y las relaciones.
     */
    protected function afterSave(): void
    {
        if ($this->stockAction === null) {
            return;
        }

        $record  = $this->getRecord();
        $service = app(InventarioCoreService::class);

        if ($this->stockAction === 'revertir') {
            $service->revertirDetalles($record->empresa_id, 'entrada', collect($this->detallesViejos));
            return;
        }

        // Para 'aplicar' y 'sincronizar', los detalles nuevos se leen de BD
        // (Filament ya los guardó antes de llamar a afterSave)
        $nuevos = $record->detalles()->with('unidad')->get();

        if ($this->stockAction === 'sincronizar') {
            // Atomizar revert + apply para evitar estado inconsistente si falla alguno
            DB::transaction(function () use ($record, $service, $nuevos): void {
                $service->revertirDetalles($record->empresa_id, 'entrada', collect($this->detallesViejos));
                $service->aplicarDetalles($record->empresa_id, 'entrada', $nuevos);
            });
            return;
        }

        // pendiente → recibido: solo aplicar los nuevos
        $service->aplicarDetalles($record->empresa_id, 'entrada', $nuevos);
    }

    private function leerDetallesDeDB(int $compraId): array
    {
        return DB::table('compra_detalles')
            ->where('compra_id', $compraId)
            ->get(['producto_id', 'variante_id', 'unidad_id', 'cantidad'])
            ->map(fn(\stdClass $row) => (array) $row)
            ->values()
            ->all();
    }
}

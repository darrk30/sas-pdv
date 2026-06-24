<?php

namespace App\Filament\Pdv\Resources\Compras\Pages;

use App\Filament\Pdv\Resources\Compras\CompraResource;
use App\Services\InventarioCoreService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;

class EditCompra extends EditRecord
{
    protected static string $resource = CompraResource::class;

    /**
     * Snapshot del estado y detalles al CARGAR el formulario, antes de cualquier edición.
     * Se usa como referencia para calcular el delta de stock al guardar.
     * #[Locked] impide que el cliente modifique estos valores entre roundtrips de Livewire.
     *
     * No se captura en mutateFormDataBeforeSave porque en Filament v5 las relaciones
     * (compra_detalles) ya están guardadas en BD antes de que ese hook se ejecute.
     */
    #[Locked]
    public string $snapshotEstado = 'pendiente';

    #[Locked]
    public array $snapshotDetalles = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Se ejecuta después de que Filament llena el formulario con los datos del registro.
     * Es el único momento seguro para capturar el estado "antes de editar".
     */
    protected function afterFill(): void
    {
        $record = $this->getRecord();

        $this->snapshotEstado   = $record->estado_despacho ?? 'pendiente';
        $this->snapshotDetalles = DB::table('compra_detalles')
            ->where('compra_id', $record->getKey())
            ->get(['producto_id', 'variante_id', 'unidad_id', 'cantidad'])
            ->map(fn(\stdClass $row) => (array) $row)
            ->values()
            ->all();
    }

    /**
     * Se ejecuta después de que Filament guarda el registro principal Y las relaciones.
     * Compara el snapshot (estado pre-edición) contra el estado actual en BD para
     * determinar qué movimiento de stock aplicar.
     */
    protected function afterSave(): void
    {
        $record      = $this->getRecord()->refresh();
        $estadoViejo = $this->snapshotEstado;
        $estadoNuevo = $record->estado_despacho;
        $service     = app(InventarioCoreService::class);

        if ($estadoViejo !== $estadoNuevo) {
            if ($estadoNuevo === 'recibido') {
                // pendiente → recibido: aplicar cantidades actuales (ya en BD)
                $service->aplicarDetalles(
                    $record->empresa_id,
                    'entrada',
                    $record->detalles()->with('unidad')->get(),
                );
            } else {
                // recibido → pendiente: revertir exactamente lo que se recibió (snapshot)
                $service->revertirDetalles(
                    $record->empresa_id,
                    'entrada',
                    collect($this->snapshotDetalles),
                );
            }
        } elseif ($estadoNuevo === 'recibido') {
            // recibido → recibido con cambios: revertir snapshot + aplicar nuevos en una sola tx
            $nuevos = $record->detalles()->with('unidad')->get();
            DB::transaction(function () use ($record, $service, $nuevos): void {
                $service->revertirDetalles($record->empresa_id, 'entrada', collect($this->snapshotDetalles));
                $service->aplicarDetalles($record->empresa_id, 'entrada', $nuevos);
            });
        }
        // pendiente → pendiente: sin acción de stock

        // Actualizar snapshot para la próxima edición en la misma sesión (SPA mode)
        $this->snapshotEstado   = $estadoNuevo;
        $this->snapshotDetalles = DB::table('compra_detalles')
            ->where('compra_id', $record->getKey())
            ->get(['producto_id', 'variante_id', 'unidad_id', 'cantidad'])
            ->map(fn(\stdClass $row) => (array) $row)
            ->values()
            ->all();
    }
}

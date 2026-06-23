<?php

namespace App\Filament\Pdv\Resources\Compras\Pages;

use App\Filament\Pdv\Resources\Compras\CompraResource;
use App\Services\InventarioCoreService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        $record = $this->getRecord();

        // Leer directamente de BD para garantizar el valor pre-guardado (sin depender del modelo)
        $estadoViejo = DB::table('compras')
            ->where('id', $record->getKey())
            ->value('estado_despacho') ?? 'pendiente';

        // Normalizar el valor nuevo: puede llegar como string o como instancia de enum
        $raw         = $data['estado_despacho'] ?? 'pendiente';
        $estadoNuevo = $raw instanceof \BackedEnum ? $raw->value : (string) $raw;

        // Capturar detalles en BD ahora (antes de que Filament sobreescriba las relaciones)
        $this->detallesAntes = $record->detalles()->with('unidad')->get();

        if ($estadoViejo !== $estadoNuevo) {
            $this->stockAction = ($estadoNuevo === 'recibido') ? 'aplicar' : 'revertir';
        } elseif ($estadoNuevo === 'recibido') {
            // Se mantiene en recibido: revertir viejos + aplicar nuevos para sincronizar cantidades
            $this->stockAction = 'sincronizar';
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
        } elseif ($this->stockAction === 'revertir') {
            $service->revertirDetalles($record->empresa_id, 'entrada', $this->detallesAntes);
        } else {
            // sincronizar: revertir cantidades viejas y aplicar las nuevas
            $service->revertirDetalles($record->empresa_id, 'entrada', $this->detallesAntes);
            $service->aplicarCompra($record);
        }
    }
}

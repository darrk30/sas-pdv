<?php

namespace App\Filament\Pdv\Resources\Ajustes\Pages;

use App\Filament\Pdv\Resources\Ajustes\AjusteResource;
use App\Models\Ajuste;
use App\Services\InventarioCoreService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Collection;

class EditAjuste extends EditRecord
{
    protected static string $resource = AjusteResource::class;

    /** Snapshot de detalles ANTES de guardar, para revertir el movimiento anterior. */
    protected ?Collection $snapshotDetalles = null;

    /** Tipo del ajuste ANTES de guardar (puede cambiar en el formulario). */
    protected ?string $snapshotTipo = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            // El AjusteObserver::deleting() revierte el stock automáticamente.
        ];
    }

    /**
     * Se ejecuta ANTES de que Filament actualice el Ajuste y sus detalles.
     * Aquí capturamos el estado actual en BD (el "viejo").
     */
    protected function beforeSave(): void
    {
        // Cargamos desde BD (no desde el modelo en memoria de Livewire)
        // para garantizar el estado real guardado antes de este guardado.
        /** @var Ajuste $ajuste */
        $ajuste = Ajuste::with('detalles.unidad')->find($this->record->id);

        $this->snapshotTipo     = $ajuste->tipo;
        $this->snapshotDetalles = $ajuste->detalles;
    }

    /**
     * Se ejecuta DESPUÉS de que Filament actualiza el Ajuste y sus detalles (Repeater).
     * Aquí: revertimos el movimiento viejo y aplicamos el nuevo.
     */
    protected function afterSave(): void
    {
        $service = app(InventarioCoreService::class);

        // 1. Revertir el movimiento guardado anteriormente
        if ($this->snapshotDetalles?->isNotEmpty()) {
            $service->revertirDetalles(
                $this->record->empresa_id,
                $this->snapshotTipo,
                $this->snapshotDetalles,
            );
        }

        // 2. Aplicar el nuevo estado (tipo y detalles actualizados)
        $service->aplicarAjuste($this->record);
    }
}

<?php

namespace App\Filament\Pdv\Resources\Ordenes\Pages;

use App\Filament\Pdv\Resources\Ordenes\Concerns\ValidaStockOrden;
use App\Filament\Pdv\Resources\Ordenes\OrdenResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateOrden extends CreateRecord
{
    use ValidaStockOrden;

    protected static string $resource = OrdenResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['vendedor_id'] = auth()->id();

        $empresaId = Filament::getTenant()?->id;
        if ($empresaId) {
            $errores = $this->validarStockDetalles(
                array_values($data['detalles'] ?? []),
                [], // no hay reservas previas al crear
                $empresaId
            );

            if (!empty($errores)) {
                foreach ($errores as $msg) {
                    Notification::make()->danger()->title('Stock insuficiente')->body($msg)->persistent()->send();
                }
                $this->halt();
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $empresaId = $this->record->empresa_id;
        $detalles  = $this->record->detalles->map(fn($d) => [
            'producto_id'  => $d->producto_id,
            'variante_id'  => $d->variante_id,
            'promocion_id' => $d->promocion_id,
            'cantidad'     => (float) $d->cantidad,
        ])->all();

        $this->reservarStockDetalles($detalles, $empresaId);
    }
}

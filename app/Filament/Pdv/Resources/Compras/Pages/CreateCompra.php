<?php

namespace App\Filament\Pdv\Resources\Compras\Pages;

use App\Filament\Pdv\Resources\Compras\CompraResource;
use App\Services\InventarioCoreService;
use Filament\Resources\Pages\CreateRecord;

class CreateCompra extends CreateRecord
{
    protected static string $resource = CompraResource::class;

    private bool  $aplicarStock    = false;
    private array $detallesAPlicar = [];

    /**
     * Captura el estado_despacho y los detalles del form ANTES del guardado,
     * igual que EditCompra, para no depender del orden afterCreate/saveRelationships.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['estado']  = 'confirmado';

        $raw = $data['estado_despacho'] ?? 'pendiente';
        $estadoDespacho = $raw instanceof \BackedEnum ? $raw->value : (string) $raw;

        if ($estadoDespacho === 'recibido') {
            $this->aplicarStock    = true;
            $this->detallesAPlicar = array_values($data['detalles'] ?? []);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->aplicarStock) {
            return;
        }

        $record = $this->getRecord();

        app(InventarioCoreService::class)->aplicarDetalles(
            $record->empresa_id,
            'entrada',
            collect($this->detallesAPlicar),
        );
    }
}

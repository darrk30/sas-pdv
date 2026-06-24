<?php

namespace App\Filament\Pdv\Resources\Compras\Pages;

use App\Filament\Pdv\Resources\Compras\CompraResource;
use App\Services\InventarioCoreService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCompra extends CreateRecord
{
    protected static string $resource = CompraResource::class;

    private bool $aplicarStock = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['estado']  = 'confirmado';

        $raw = $data['estado_despacho'] ?? 'pendiente';
        $estadoDespacho = $raw instanceof \BackedEnum ? $raw->value : (string) $raw;

        $this->aplicarStock = ($estadoDespacho === 'recibido');

        $tipo = $data['tipo_comprobante'] ?? '';
        $tipo = $tipo instanceof \BackedEnum ? $tipo->value : (string) $tipo;

        if ($tipo !== 'sin_comprobante') {
            $serie       = $data['serie'] ?? null;
            $correlativo = $data['correlativo'] ?? null;

            if ($serie && $correlativo) {
                $codigo    = $serie . '-' . $correlativo;
                $empresaId = Filament::getTenant()?->id;

                $existe = DB::table('compras')
                    ->where('empresa_id', $empresaId)
                    ->where('codigo', $codigo)
                    ->exists();

                if ($existe) {
                    throw ValidationException::withMessages([
                        'data.correlativo' => "El comprobante {$codigo} ya está registrado.",
                    ]);
                }
            }
        }

        return $data;
    }

    /**
     * Lee los detalles directamente de BD (Filament ya guardó las relaciones
     * antes de llamar a afterCreate), así se evitan problemas de formato del $data del form.
     */
    protected function afterCreate(): void
    {
        if (! $this->aplicarStock) {
            return;
        }

        $record = $this->getRecord();

        app(InventarioCoreService::class)->aplicarDetalles(
            $record->empresa_id,
            'entrada',
            $record->detalles()->with('unidad')->get(),
        );
    }
}

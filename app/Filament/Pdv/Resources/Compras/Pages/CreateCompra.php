<?php

namespace App\Filament\Pdv\Resources\Compras\Pages;

use App\Filament\Pdv\Resources\Compras\CompraResource;
use App\Services\InventarioCoreService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
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

    protected function beforeCreate(): void
    {
        $this->validarPagos();
    }

    private function validarPagos(): void
    {
        $estadoPago = $this->data['estado_pago'] ?? 'pendiente';
        $estadoPago = $estadoPago instanceof \BackedEnum ? $estadoPago->value : (string) $estadoPago;

        if ($estadoPago === 'pendiente') return;

        $total     = round((float) ($this->data['total'] ?? 0), 2);
        $pagos     = is_array($this->data['pagos'] ?? null) ? $this->data['pagos'] : [];
        $sumaPagos = 0.0;
        foreach ($pagos as $pago) {
            if (is_array($pago)) $sumaPagos += (float) ($pago['monto'] ?? 0);
        }
        $sumaPagos = round($sumaPagos, 2);

        $error = null;

        if ($estadoPago === 'pagado' && $sumaPagos !== $total) {
            $error = "El monto pagado (S/ {$sumaPagos}) debe coincidir con el total (S/ {$total}).";
        } elseif ($estadoPago === 'parcial') {
            if ($sumaPagos <= 0) {
                $error = 'Debe registrar al menos un pago cuando el estado es Parcial.';
            } elseif ($sumaPagos > $total) {
                $error = "El monto pagado (S/ {$sumaPagos}) no puede superar el total (S/ {$total}).";
            }
        }

        if ($error) {
            throw ValidationException::withMessages(['data.pagos' => $error]);
        }
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

        app(InventarioCoreService::class)->aplicarCompra($record);
    }
}

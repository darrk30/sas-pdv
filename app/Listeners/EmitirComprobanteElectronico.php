<?php

namespace App\Listeners;

use App\Enums\EstadoSunat;
use App\Enums\TipoComprobante;
use App\Events\VentaCompletada;
use App\Services\FacturadorService;
use Illuminate\Support\Facades\Log;

class EmitirComprobanteElectronico
{
    public function __construct(private FacturadorService $service) {}

    public function handle(VentaCompletada $event): void
    {
        $venta   = $event->venta;
        $empresa = $venta->empresa ?? $venta->load('empresa')->empresa;

        if (! $empresa->tieneFacturacionElectronica()) {
            return;
        }

        $tipo = $venta->serie?->tipo;

        // Las boletas pueden ir en resumen; solo se envían directo si la empresa lo tiene activo
        if ($tipo === TipoComprobante::Boleta && ! $empresa->fe_envio_directo_boleta) {
            $venta->update(['estado_sunat' => EstadoSunat::PorEnviar]);
            return;
        }

        // Las facturas se envían de forma directa (sincrónica en SUNAT)
        if ($tipo === TipoComprobante::Factura && ! $empresa->fe_envio_directo_factura) {
            $venta->update(['estado_sunat' => EstadoSunat::PorEnviar]);
            return;
        }

        try {
            $response = $this->service->enviarComprobante($venta, enviarSunat: true);

            if ($response->ok) {
                $venta->update([
                    'hash'          => $response->hash,
                    'sunat_success' => true,
                    'estado_sunat'  => $tipo === TipoComprobante::Boleta
                        ? EstadoSunat::EnResumen
                        : EstadoSunat::Aceptado,
                    'sunat_notas'   => $response->sunatNotes ?: null,
                ]);
            } else {
                $venta->update([
                    'sunat_success' => false,
                    'sunat_mensaje' => $response->mensajeError(),
                    'estado_sunat'  => EstadoSunat::Error,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('EmitirComprobanteElectronico: excepción inesperada', [
                'venta_id' => $venta->id,
                'error'    => $e->getMessage(),
            ]);

            $venta->update([
                'sunat_success' => false,
                'sunat_mensaje' => $e->getMessage(),
                'estado_sunat'  => EstadoSunat::Error,
            ]);
        }
    }
}

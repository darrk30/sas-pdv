<?php

namespace App\Listeners;

use App\Enums\EstadoSunat;
use App\Enums\TipoComprobante;
use App\Events\VentaCompletada;
use App\Models\Venta;
use App\Services\FacturadorResponse;
use App\Services\FacturadorService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        if (! in_array($tipo, [TipoComprobante::Boleta, TipoComprobante::Factura])) {
            return;
        }

        // Determinar si enviar a SUNAT inmediatamente o solo generar XML
        $enviarSunat = match ($tipo) {
            TipoComprobante::Boleta  => (bool) $empresa->fe_envio_directo_boleta,
            TipoComprobante::Factura => (bool) $empresa->fe_envio_directo_factura,
        };

        try {
            $response = $this->service->enviarComprobante($venta, enviarSunat: $enviarSunat);

            if ($enviarSunat) {
                // ── Envío directo a SUNAT ─────────────────────────────────
                if ($response->ok) {
                    $this->guardarArchivos($venta, $response);
                    $venta->update([
                        'hash'              => $response->hash,
                        'sunat_success'     => true,
                        'estado_sunat'      => EstadoSunat::Aceptado,
                        'sunat_codigo'      => $response->sunatCode,
                        'sunat_descripcion' => $response->sunatDescription,
                        'sunat_notas'       => $response->sunatNotes ?: null,
                        'qr_data'           => $response->qrData,
                        'total_letras'      => $response->totalLetras,
                    ]);
                } else {
                    $venta->update([
                        'sunat_success' => false,
                        'sunat_mensaje' => $response->mensajeError(),
                        'estado_sunat'  => EstadoSunat::Error,
                    ]);
                }
            } else {
                // ── Solo generar XML — envío a SUNAT queda pendiente ─────
                if ($response->ok) {
                    $this->guardarArchivos($venta, $response);
                    $venta->update(array_filter([
                        'hash'         => $response->hash,
                        'qr_data'      => $response->qrData,
                        'total_letras' => $response->totalLetras,
                        'estado_sunat' => EstadoSunat::PorEnviar,
                    ], fn ($v) => $v !== null));
                } else {
                    // El facturador falló al generar el XML
                    $venta->update([
                        'sunat_success' => false,
                        'sunat_mensaje' => $response->mensajeError(),
                        'estado_sunat'  => EstadoSunat::Error,
                    ]);
                }
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

    private function guardarArchivos(Venta $venta, FacturadorResponse $response): void
    {
        $serie       = $venta->serie->serie ?? 'X';
        $correlativo = str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT);
        $base        = "empresas/{$venta->empresa_id}/comprobantes/{$serie}-{$correlativo}";

        if ($response->xmlBase64) {
            $pathXml = "{$base}.xml";
            Storage::disk('local')->put($pathXml, base64_decode($response->xmlBase64));
            $venta->path_xml = $pathXml;
        }

        if ($response->cdrZip) {
            $pathCdr = "{$base}-CDR.zip";
            Storage::disk('local')->put($pathCdr, base64_decode($response->cdrZip));
            $venta->path_cdr_zip = $pathCdr;
        }
    }
}

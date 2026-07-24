<?php

namespace App\Filament\Pdv\Concerns;

use App\Enums\EstadoSunat;
use App\Models\Venta;
use App\Services\FacturadorService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

trait HasEnvioVentaDirecto
{
    public function enviarAhora(Venta $venta): void
    {
        $venta->loadMissing(['empresa.facturacion', 'serie', 'detalles.producto']);

        if (! $venta->empresa->tieneFacturacionElectronica()) {
            Notification::make()->title('La empresa no tiene FE configurada')->danger()->send();
            return;
        }

        try {
            /** @var FacturadorService $service */
            $service  = app(FacturadorService::class);
            $response = $service->enviarComprobante($venta, enviarSunat: true);
            if ($response->ok) {
                $serie       = $venta->serie->serie ?? 'X';
                $correlativo = str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT);
                $base        = "empresas/{$venta->empresa_id}/comprobantes/{$serie}-{$correlativo}";

                $pathXml = $pathCdr = null;

                if ($response->xmlBase64) {
                    $pathXml = "{$base}.xml";
                    Storage::disk('local')->put($pathXml, base64_decode($response->xmlBase64));
                }

                if ($response->cdrZip) {
                    $pathCdr = "{$base}-CDR.zip";
                    Storage::disk('local')->put($pathCdr, base64_decode($response->cdrZip));
                }

                $venta->update(array_filter([
                    'path_xml'          => $pathXml,
                    'path_cdr_zip'      => $pathCdr,
                    'hash'              => $response->hash,
                    'sunat_success'     => true,
                    'estado_sunat'      => EstadoSunat::Aceptado,
                    'sunat_codigo'      => $response->sunatCode,
                    'sunat_descripcion' => $response->sunatDescription,
                    'sunat_notas'       => $response->sunatNotes ?: null,
                    'qr_data'           => $response->qrData,
                    'total_letras'      => $response->totalLetras,
                ], fn ($v) => $v !== null));

                Notification::make()
                    ->title('Comprobante aceptado por SUNAT')
                    ->body($response->sunatDescription)
                    ->success()
                    ->send();
            } else {
                $venta->update([
                    'sunat_success' => false,
                    'sunat_mensaje' => $response->mensajeError(),
                    'estado_sunat'  => EstadoSunat::Error,
                ]);

                Notification::make()
                    ->title('SUNAT rechazó el comprobante')
                    ->body($response->mensajeError())
                    ->danger()
                    ->send();
            }
        } catch (\Throwable $e) {
            $venta->update([
                'sunat_success' => false,
                'sunat_mensaje' => $e->getMessage(),
                'estado_sunat'  => EstadoSunat::Error,
            ]);

            Notification::make()
                ->title('Error al enviar a SUNAT')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}

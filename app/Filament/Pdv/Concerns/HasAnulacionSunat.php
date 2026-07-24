<?php

namespace App\Filament\Pdv\Concerns;

use App\Enums\EstadoSunat;
use App\Enums\TipoComprobante;
use App\Models\ResumenSunat;
use App\Models\Venta;
use App\Services\FacturadorService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Storage;

trait HasAnulacionSunat
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function estadoNecesitaBaja(Venta $venta): bool
    {
        $estado = $venta->estado_sunat instanceof EstadoSunat
            ? $venta->estado_sunat
            : EstadoSunat::tryFrom((string) $venta->estado_sunat);

        return in_array($estado, [
            EstadoSunat::Aceptado,
            EstadoSunat::Enviado,
            EstadoSunat::EnResumen,
            EstadoSunat::Observado,
        ], strict: true);
    }

    protected function esFactura(Venta $venta): bool
    {
        $tipo = $venta->serie?->tipo;

        return ($tipo instanceof TipoComprobante)
            ? $tipo === TipoComprobante::Factura
            : $tipo === TipoComprobante::Factura->value;
    }

    // ── Baja principal ────────────────────────────────────────────────────────

    /**
     * Envía la baja a SUNAT según el tipo de comprobante:
     *
     * • Boleta  → nuevo RC con la boleta en estado="3" (baja) vía /api/summaries/send
     *             (SUNAT no acepta RA para boletas — error 2308)
     * • Factura → Comunicación de Baja (RA) vía /api/voids/send
     */
    protected function enviarBajaASunat(Venta $venta, string $motivo): void
    {
        $empresa = Filament::getTenant();
        $empresa->loadMissing('facturacion');

        if (! $empresa->tieneFacturacionElectronica()) {
            return;
        }

        $venta->loadMissing('serie');

        if (! $this->esFactura($venta)) {
            $this->enviarBajaBoletaViaRC($venta, $empresa);
        } else {
            $this->enviarBajaFacturaViaRA($venta, $empresa, $motivo);
        }
    }

    // ── Boleta: RC con estado="3" ─────────────────────────────────────────────

    private function enviarBajaBoletaViaRC(Venta $venta, $empresa): void
    {
        // Marcar PorDarBaja ANTES de enviar: buildSummaryDetail usa este estado → "3"
        $venta->update(['estado_sunat' => EstadoSunat::PorDarBaja->value]);
        $venta->estado_sunat = EstadoSunat::PorDarBaja;

        $fechaRef    = Carbon::parse($venta->fecha_emision)->toDateString();
        $existing    = ResumenSunat::where('empresa_id', $empresa->id)
            ->whereIn('tipo', ['diario', 'notas_diario'])
            ->whereDate('fecha_referencia', $fechaRef)
            ->count();
        $nro         = str_pad((string) ($existing + 1), 3, '0', STR_PAD_LEFT);
        $correlativo = 'RC-' . Carbon::parse($fechaRef)->format('Ymd') . '-' . $nro;

        $resumen = ResumenSunat::create([
            'empresa_id'       => $empresa->id,
            'tipo'             => 'diario',
            'fecha_referencia' => $fechaRef,
            'correlativo'      => $correlativo,
            'estado_sunat'     => EstadoSunat::Pendiente->value,
        ]);

        try {
            $service  = app(FacturadorService::class);
            $response = $service->enviarResumen($resumen, new EloquentCollection([$venta]));

            if ($response->ok) {
                $pathXml = null;
                if ($response->xmlBase64) {
                    $pathXml = "empresas/{$empresa->id}/resumenes/{$correlativo}.xml";
                    Storage::disk('local')->put($pathXml, base64_decode($response->xmlBase64));
                }

                $resumen->update([
                    'ticket_sunat' => $response->ticket,
                    'hash'         => $response->hash,
                    'path_xml'     => $pathXml,
                    'estado_sunat' => EstadoSunat::Enviado->value,
                    'fecha_envio'  => now(),
                ]);

                $venta->update(['resumen_sunat_id' => $resumen->id]);

                Notification::make()
                    ->title("Resumen de baja {$correlativo} enviado a SUNAT")
                    ->body('Ticket: ' . $response->ticket . '. Ve a "Resumen de Boletas" y usa "Consultar estado" para obtener el CDR.')
                    ->success()
                    ->persistent()
                    ->send();
            } else {
                $resumen->update([
                    'estado_sunat' => EstadoSunat::Error->value,
                    'sunat_error'  => $response->mensajeError(),
                    'fecha_envio'  => now(),
                ]);

                Notification::make()
                    ->title('Error al enviar baja de boleta')
                    ->body($response->mensajeError() . ' La boleta quedó como "Por dar de baja". Puedes reintentarlo desde "Resumen de Boletas".')
                    ->danger()
                    ->send();
            }
        } catch (\Throwable $e) {
            $resumen->update([
                'estado_sunat' => EstadoSunat::Error->value,
                'sunat_error'  => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Error al enviar baja de boleta')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ── Factura: RA (Comunicación de Baja) ───────────────────────────────────

    private function enviarBajaFacturaViaRA(Venta $venta, $empresa, string $motivo): void
    {
        $hoy      = now();
        $existing = ResumenSunat::where('empresa_id', $empresa->id)
            ->whereIn('tipo', ['bajas', 'notas_bajas'])
            ->whereDate('fecha_referencia', $hoy)
            ->count();

        $nro         = str_pad((string) ($existing + 1), 3, '0', STR_PAD_LEFT);
        $correlativo = 'RA-' . $hoy->format('Ymd') . '-' . $nro;

        $resumen = ResumenSunat::create([
            'empresa_id'       => $empresa->id,
            'tipo'             => 'bajas',
            'fecha_referencia' => $hoy->toDateString(),
            'correlativo'      => $correlativo,
            'estado_sunat'     => EstadoSunat::Pendiente->value,
        ]);

        try {
            $service  = app(FacturadorService::class);
            $response = $service->enviarBaja($resumen, new EloquentCollection([$venta]), $motivo);

            if ($response->ok) {
                $resumen->update([
                    'ticket_sunat' => $response->ticket,
                    'hash'         => $response->hash,
                    'estado_sunat' => EstadoSunat::Enviado->value,
                    'fecha_envio'  => now(),
                ]);

                $venta->update([
                    'estado_sunat'     => EstadoSunat::PorDarBaja->value,
                    'resumen_sunat_id' => $resumen->id,
                ]);

                Notification::make()
                    ->title("Baja {$correlativo} enviada a SUNAT")
                    ->body('Ticket: ' . $response->ticket . '. Usa "Consultar baja" en las acciones de esta fila para obtener el CDR.')
                    ->success()
                    ->persistent()
                    ->send();
            } else {
                $resumen->update([
                    'estado_sunat' => EstadoSunat::Error->value,
                    'sunat_error'  => $response->mensajeError(),
                    'fecha_envio'  => now(),
                ]);

                Notification::make()
                    ->title('Error al enviar baja a SUNAT')
                    ->body($response->mensajeError())
                    ->danger()
                    ->send();
            }
        } catch (\Throwable $e) {
            $resumen->update([
                'estado_sunat' => EstadoSunat::Error->value,
                'sunat_error'  => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Error al enviar baja')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ── Consultar ticket de RA (solo facturas) ────────────────────────────────

    protected function consultarBajaVenta(Venta $venta): void
    {
        $empresa = Filament::getTenant();
        $empresa->loadMissing('facturacion');

        $venta->loadMissing(['serie', 'resumenSunat']);
        $resumen = $venta->resumenSunat;

        if (! $resumen || ! $resumen->tipo->esRA() || ! $resumen->ticket_sunat) {
            Notification::make()
                ->title('No hay ticket de baja para consultar')
                ->warning()
                ->send();
            return;
        }

        $config = $empresa->facturacion;
        if (! $config) {
            Notification::make()->title('Sin configuración FE')->danger()->send();
            return;
        }

        try {
            $service  = app(FacturadorService::class);
            $response = $service->consultarEstadoBaja($config, $resumen->ticket_sunat);

            if ($response->ok) {
                $pathCdr = null;
                if ($response->cdrZip) {
                    $pathCdr = "empresas/{$empresa->id}/bajas/{$resumen->correlativo}-CDR.zip";
                    Storage::disk('local')->put($pathCdr, base64_decode($response->cdrZip));
                }

                $resumen->update([
                    'sunat_success'     => true,
                    'sunat_codigo'      => $response->sunatCode,
                    'sunat_descripcion' => $response->sunatDescription,
                    'sunat_notas'       => $response->sunatNotes ? json_encode($response->sunatNotes) : null,
                    'path_cdr_zip'      => $pathCdr,
                    'estado_sunat'      => EstadoSunat::Aceptado->value,
                    'fecha_respuesta'   => now(),
                ]);

                $venta->update(['estado_sunat' => EstadoSunat::DadoDeBaja->value]);

                Notification::make()
                    ->title('Baja aceptada por SUNAT')
                    ->body("[{$response->sunatCode}] {$response->sunatDescription}")
                    ->success()
                    ->send();
            } else {
                $resumen->update([
                    'sunat_success'     => false,
                    'sunat_codigo'      => $response->sunatCode ?? $response->errorCode,
                    'sunat_descripcion' => $response->sunatDescription ?? $response->errorMessage,
                    'estado_sunat'      => EstadoSunat::Rechazado->value,
                    'fecha_respuesta'   => now(),
                ]);

                Notification::make()
                    ->title('SUNAT rechazó la baja')
                    ->body($response->mensajeError())
                    ->danger()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al consultar estado de baja')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}

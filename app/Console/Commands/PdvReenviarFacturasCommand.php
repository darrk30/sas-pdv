<?php

namespace App\Console\Commands;

use App\Enums\EstadoSunat;
use App\Enums\TipoComprobante;
use App\Models\Venta;
use App\Services\FacturadorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PdvReenviarFacturasCommand extends Command
{
    protected $signature = 'pdv:reenviar-facturas {empresa_id : ID de la empresa}';
    protected $description = 'Reenvía a SUNAT las facturas con estado PorEnviar (últimos 3 días)';

    public function handle(FacturadorService $service): int
    {
        $empresaId = (int) $this->argument('empresa_id');

        $facturas = Venta::where('empresa_id', $empresaId)
            ->where('estado_sunat', EstadoSunat::PorEnviar)
            ->whereHas('serie', fn ($q) => $q->where('tipo', TipoComprobante::Factura))
            ->where('fecha_emision', '>=', now()->subDays(3))
            ->with(['empresa.facturacion', 'serie', 'detalles.producto'])
            ->orderBy('fecha_emision')
            ->get();

        if ($facturas->isEmpty()) {
            $this->info("Empresa {$empresaId}: sin facturas pendientes.");
            return self::SUCCESS;
        }

        $this->info("Empresa {$empresaId}: procesando {$facturas->count()} factura(s)...");

        $aceptadas = 0;
        $errores   = 0;

        foreach ($facturas as $venta) {
            $serie = $venta->serie->serie ?? 'X';
            $corr  = str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT);
            $base  = "empresas/{$venta->empresa_id}/comprobantes/{$serie}-{$corr}";

            try {
                $response = $service->enviarComprobante($venta, enviarSunat: true);

                if ($response->ok) {
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

                    $venta->update(array_filter([
                        'path_xml'          => $venta->path_xml,
                        'path_cdr_zip'      => $venta->path_cdr_zip ?? null,
                        'hash'              => $response->hash,
                        'sunat_success'     => true,
                        'estado_sunat'      => EstadoSunat::Aceptado,
                        'sunat_codigo'      => $response->sunatCode,
                        'sunat_descripcion' => $response->sunatDescription,
                        'sunat_notas'       => $response->sunatNotes ?: null,
                        'qr_data'           => $response->qrData,
                        'total_letras'      => $response->totalLetras,
                    ], fn ($v) => $v !== null));

                    $aceptadas++;
                    $this->line("  ✔ {$serie}-{$corr} aceptada");

                } elseif ($response->xmlBase64) {
                    // SUNAT sigue sin disponible — actualizar error pero mantener PorEnviar
                    Storage::disk('local')->put("{$base}.xml", base64_decode($response->xmlBase64));

                    $venta->update(array_filter([
                        'path_xml'          => "{$base}.xml",
                        'hash'              => $response->hash,
                        'qr_data'           => $response->qrData,
                        'total_letras'      => $response->totalLetras,
                        'sunat_success'     => false,
                        'sunat_codigo'      => $response->errorCode,
                        'sunat_descripcion' => $response->mensajeError(),
                        'estado_sunat'      => EstadoSunat::PorEnviar,
                    ], fn ($v) => $v !== null));

                    $errores++;
                    $this->warn("  ⚠ {$serie}-{$corr} SUNAT no disponible: " . $response->mensajeError());

                } else {
                    // El facturador no pudo generar el XML
                    $venta->update([
                        'sunat_success'     => false,
                        'sunat_codigo'      => $response->errorCode,
                        'sunat_descripcion' => $response->mensajeError(),
                        'estado_sunat'      => EstadoSunat::Error,
                    ]);

                    $errores++;
                    $this->error("  ✘ {$serie}-{$corr} error: " . $response->mensajeError());
                }

            } catch (\Throwable $e) {
                Log::error('PdvReenviarFacturasCommand: excepción inesperada', [
                    'venta_id'   => $venta->id,
                    'empresa_id' => $empresaId,
                    'error'      => $e->getMessage(),
                ]);

                $venta->update([
                    'sunat_success'     => false,
                    'sunat_descripcion' => $e->getMessage(),
                    'estado_sunat'      => EstadoSunat::Error,
                ]);

                $errores++;
                $this->error("  ✘ {$serie}-{$corr} excepción: " . $e->getMessage());
            }
        }

        $this->info("Empresa {$empresaId}: {$aceptadas} aceptada(s), {$errores} con problemas.");

        return self::SUCCESS;
    }
}

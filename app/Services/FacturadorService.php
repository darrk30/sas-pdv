<?php

namespace App\Services;

use App\Enums\EstadoSunat;
use App\Enums\TipoComprobante;
use App\Models\EmpresaFacturacion;
use App\Models\Empresa;
use App\Models\Nota;
use App\Models\ResumenSunat;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacturadorService
{
    // ── Mapeos SUNAT ─────────────────────────────────────────────────────────

    /** Convierte el valor de TipoDocumento al código SUNAT del cliente */
    private function mapTipoDocCliente(?string $tipo): string
    {
        return match ($tipo) {
            'ruc'   => '6',
            'dni'   => '1',
            default => '-',
        };
    }

    /** Convierte TipoComprobante al código SUNAT del tipoDoc */
    private function mapTipoDocComprobante(TipoComprobante $tipo): string
    {
        return match ($tipo) {
            TipoComprobante::Factura     => '01',
            TipoComprobante::Boleta      => '03',
            TipoComprobante::NotaCredito => '07',
            TipoComprobante::NotaDebito  => '08',
            default                      => '03',
        };
    }

    // ── Builders de payload ───────────────────────────────────────────────────

    private function buildCompany(Empresa $empresa): array
    {
        return [
            'ruc'             => $empresa->ruc,
            'razonSocial'     => $empresa->name,
            'nombreComercial' => $empresa->name,
            'email'           => $empresa->email,
            'telefono'        => $empresa->telefono,
            'address'         => [
                'ubigeo'      => $empresa->ubigeo,
                'departamento'=> $empresa->departamento,
                'provincia'   => $empresa->provincia,
                'distrito'    => $empresa->distrito,
                'direccion'   => $empresa->direccion,
                'codLocal'    => $empresa->cod_local ?? '0000',
            ],
        ];
    }

    private function buildClient(Venta $venta): array
    {
        return [
            'tipoDoc'     => $this->mapTipoDocCliente($venta->cliente_tipo_doc),
            'numDoc'      => $venta->cliente_num_doc ?? '-',
            'razonSocial' => $venta->cliente_nombre ?? 'CLIENTE',
        ];
    }

    private function buildDetails(Collection $detalles, float $igvPorcentaje): array
    {
        return $detalles->map(function (VentaDetalle $d) use ($igvPorcentaje): array {
            $codProducto = $d->producto?->codigo_interno
                ?? $d->producto?->codigo_barras
                ?? (string) $d->producto_id;

            return [
                'tipAfeIgv'         => (int) ($d->tip_afe_igv ?? '10'),
                'codProducto'       => $codProducto,
                'unidad'            => $d->unidad ?? 'NIU',
                'descripcion'       => $d->descripcion,
                'cantidad'          => (float) $d->cantidad,
                'mtoValorUnitario'  => (float) $d->valor_unitario,
                'mtoPrecioUnitario' => (float) $d->precio_unitario,
                'mtoValorVenta'     => (float) $d->valor_total,
                'mtoBaseIgv'        => (float) $d->valor_total,
                'porcentajeIgv'     => $igvPorcentaje,
                'igv'               => (float) $d->igv,
                'totalImpuestos'    => (float) $d->igv,
                'factorIcbper'      => null,
                'icbper'            => 0,
            ];
        })->values()->all();
    }

    /** Construye un ítem del details[] para resúmenes diarios (RC) */
    private function buildSummaryDetail(Venta $venta): array
    {
        $serie    = $venta->serie;
        $serieNro = $serie->serie . '-' . str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT);

        $estadoSunat = $venta->estado_sunat instanceof EstadoSunat
            ? $venta->estado_sunat
            : EstadoSunat::tryFrom((string) $venta->estado_sunat);

        $estado = ($estadoSunat === EstadoSunat::DadoDeBaja) ? '3' : '1';

        return [
            'tipoDoc'            => $this->mapTipoDocComprobante($serie->tipo),
            'serieNro'           => $serieNro,
            'estado'             => $estado,
            'clienteTipo'        => $this->mapTipoDocCliente($venta->cliente_tipo_doc),
            'clienteNro'         => $venta->cliente_num_doc ?? '-',
            'total'              => (float) $venta->total,
            'mtoOperGravadas'    => (float) ($venta->op_gravadas ?? 0),
            'mtoOperExoneradas'  => (float) ($venta->op_exoneradas ?? 0),
            'mtoOperInafectas'   => (float) ($venta->op_inafectas ?? 0),
            'mtoOperExportacion' => 0.0,
            'mtoOtrosCargos'     => 0.0,
            'porcentajeIgv'      => 18.0,
            'mtoIGV'             => (float) $venta->igv,
        ];
    }

    // ── HTTP ──────────────────────────────────────────────────────────────────

    /** Realiza el POST al facturador y devuelve el JSON o null si hay error de transporte */
    private function http(EmpresaFacturacion $config, string $endpoint, array $body): ?array
    {
        try {
            $url      = rtrim($config->facturador_url, '/') . $endpoint;
            $response = Http::withToken($config->facturador_api_token)
                ->timeout(30)
                ->post($url, $body);

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('FacturadorService: error HTTP', [
                'endpoint' => $endpoint,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ── API pública ───────────────────────────────────────────────────────────

    /**
     * Envía una boleta o factura al facturador.
     * Si $enviarSunat=false, solo genera y firma el XML (envío diferido o manual).
     */
    public function enviarComprobante(Venta $venta, bool $enviarSunat = true): FacturadorResponse
    {
        $venta->loadMissing(['empresa.facturacion', 'serie', 'detalles.producto']);

        $empresa = $venta->empresa;
        $config  = $empresa?->facturacion;

        if (! $config) {
            return FacturadorResponse::fromError('Empresa sin configuración de facturación.');
        }

        $body = [
            'company'       => $this->buildCompany($empresa),
            'client'        => $this->buildClient($venta),
            'tipoDoc'       => $this->mapTipoDocComprobante($venta->serie->tipo),
            'tipoOperacion' => '0101',
            'serie'         => $venta->serie->serie,
            'correlativo'   => str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT),
            'fechaEmision'  => $venta->fecha_emision->format('Y-m-d\TH:i:s'),
            'tipoMoneda'    => 'PEN',
            'enviar_sunat'  => $enviarSunat,
            'details'       => $this->buildDetails(
                $venta->detalles,
                (float) ($empresa->igv_porcentaje ?? 18)
            ),
        ];

        $json = $this->http($config, '/api/invoices/send', $body);
        if ($json === null) {
            return FacturadorResponse::fromError('Error de conexión con el facturador.');
        }

        return FacturadorResponse::fromInvoice($json);
    }

    /**
     * Envía una nota de crédito o débito al facturador.
     * Las notas siempre se envían directamente a SUNAT (sin resumen).
     *
     * @param  Nota   $nota          La nota a emitir (con serie y motivo ya definidos)
     * @param  Venta  $ventaOriginal La venta afectada por la nota
     */
    public function enviarNota(Nota $nota, Venta $ventaOriginal): FacturadorResponse
    {
        $nota->loadMissing(['empresa.facturacion', 'serie']);
        $ventaOriginal->loadMissing(['serie', 'detalles.producto']);

        $empresa = $nota->empresa;
        $config  = $empresa?->facturacion;

        if (! $config) {
            return FacturadorResponse::fromError('Empresa sin configuración de facturación.');
        }

        $tipoDoc = $nota->tipo === 'credito' ? '07' : '08';

        $numDocAfectado = $ventaOriginal->serie->serie . '-'
            . str_pad((string) $ventaOriginal->correlativo, 8, '0', STR_PAD_LEFT);

        $body = [
            'company'        => $this->buildCompany($empresa),
            'client'         => $this->buildClient($ventaOriginal),
            'tipoDoc'        => $tipoDoc,
            'serie'          => $nota->serie->serie,
            'correlativo'    => str_pad((string) $nota->correlativo, 8, '0', STR_PAD_LEFT),
            'fechaEmision'   => $nota->fecha_emision->format('Y-m-d\TH:i:s'),
            'tipoMoneda'     => 'PEN',
            'tipDocAfectado' => $this->mapTipoDocComprobante($ventaOriginal->serie->tipo),
            'numDocAfectado' => $numDocAfectado,
            'codMotivo'      => $nota->motivo_codigo,
            'desMotivo'      => $nota->motivo_descripcion,
            'details'        => $this->buildDetails(
                $ventaOriginal->detalles,
                (float) ($empresa->igv_porcentaje ?? 18)
            ),
        ];

        $json = $this->http($config, '/api/notes/send', $body);
        if ($json === null) {
            return FacturadorResponse::fromError('Error de conexión con el facturador.');
        }

        return FacturadorResponse::fromInvoice($json);
    }

    /**
     * Envía un resumen diario de boletas (RC-YYYYMMDD-NNN) al facturador.
     * Es asíncrono: devuelve un ticket que se consulta con consultarEstadoResumen().
     *
     * @param  ResumenSunat  $resumen  El registro del resumen a enviar
     * @param  Collection    $ventas   Las boletas del día (con relación serie cargada)
     */
    public function enviarResumen(ResumenSunat $resumen, Collection $ventas): FacturadorResponse
    {
        $resumen->loadMissing('empresa.facturacion');
        $ventas->loadMissing('serie');

        $empresa = $resumen->empresa;
        $config  = $empresa?->facturacion;

        if (! $config) {
            return FacturadorResponse::fromError('Empresa sin configuración de facturación.');
        }

        $body = [
            'company'         => ['ruc' => $empresa->ruc],
            'fechaGeneracion' => now()->format('Y-m-d'),
            'fechaResumen'    => $resumen->fecha_referencia->format('Y-m-d'),
            'correlativo'     => $resumen->correlativo,
            'details'         => $ventas->map(fn(Venta $v) => $this->buildSummaryDetail($v))->values()->all(),
        ];

        $json = $this->http($config, '/api/summaries/send', $body);
        if ($json === null) {
            return FacturadorResponse::fromError('Error de conexión con el facturador.');
        }

        return FacturadorResponse::fromSummaryEnvio($json);
    }

    /**
     * Envía una comunicación de baja (RA-YYYYMMDD-NNN) para anular comprobantes ante SUNAT.
     * Es asíncrono: devuelve un ticket que se consulta con consultarEstadoBaja().
     *
     * @param  ResumenSunat  $resumen  El registro de la baja a enviar
     * @param  Collection    $ventas   Los comprobantes a anular (con relación serie cargada)
     * @param  string        $motivo   Motivo de anulación aplicado a todos los ítems
     */
    public function enviarBaja(ResumenSunat $resumen, Collection $ventas, string $motivo = 'Error en emisión'): FacturadorResponse
    {
        $resumen->loadMissing('empresa.facturacion');
        $ventas->loadMissing('serie');

        $empresa = $resumen->empresa;
        $config  = $empresa?->facturacion;

        if (! $config) {
            return FacturadorResponse::fromError('Empresa sin configuración de facturación.');
        }

        $details = $ventas->map(function (Venta $venta) use ($motivo): array {
            return [
                'tipoDoc'    => $this->mapTipoDocComprobante($venta->serie->tipo),
                'serie'      => $venta->serie->serie,
                'correlativo'=> str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT),
                'motivo'     => $motivo,
            ];
        })->values()->all();

        $body = [
            'correlativo'       => $resumen->correlativo,
            'fechaGeneracion'   => now()->format('Y-m-d'),
            'fechaComunicacion' => now()->format('Y-m-d'),
            'details'           => $details,
        ];

        $json = $this->http($config, '/api/voids/send', $body);
        if ($json === null) {
            return FacturadorResponse::fromError('Error de conexión con el facturador.');
        }

        return FacturadorResponse::fromSummaryEnvio($json);
    }

    /**
     * Consulta el estado de un resumen diario en SUNAT usando el ticket recibido.
     * Llama a POST /api/summaries/status y devuelve el CDR con el resultado definitivo.
     */
    public function consultarEstadoResumen(EmpresaFacturacion $config, string $ticket): FacturadorResponse
    {
        $config->loadMissing('empresa');

        $json = $this->http($config, '/api/summaries/status', [
            'company' => ['ruc' => $config->empresa->ruc],
            'ticket'  => $ticket,
        ]);

        if ($json === null) {
            return FacturadorResponse::fromError('Error de conexión con el facturador.');
        }

        return FacturadorResponse::fromStatus($json);
    }

    /**
     * Consulta el estado de una comunicación de baja en SUNAT usando el ticket.
     * Llama a POST /api/voids/status.
     */
    public function consultarEstadoBaja(EmpresaFacturacion $config, string $ticket): FacturadorResponse
    {
        $json = $this->http($config, '/api/voids/status', [
            'ticket' => $ticket,
        ]);

        if ($json === null) {
            return FacturadorResponse::fromError('Error de conexión con el facturador.');
        }

        return FacturadorResponse::fromStatus($json);
    }
}

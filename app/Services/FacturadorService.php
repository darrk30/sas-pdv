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
use Illuminate\Support\Facades\Storage;

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

    private function buildDetails(Collection $detalles, float $igvPorcentaje, float $discountRatio = 1.0): array
    {
        $divisor = 1 + $igvPorcentaje / 100;

        return $detalles->values()->map(function (VentaDetalle $d) use (
            $igvPorcentaje, $divisor, $discountRatio
        ): array {
            $codProducto = $d->producto?->codigo_interno
                ?? $d->producto?->codigo_barras
                ?? (string) $d->producto_id;

            $precioUnit = round((float) $d->precio_unitario * $discountRatio, 2);
            $cantidad   = (float) $d->cantidad;
            $subtotal   = round($precioUnit * $cantidad, 2);

            // igv = subtotal − op_gravada (sustracción; más exacto que multiplicar)
            $valorVenta    = round($subtotal / $divisor, 2);
            $igv           = round($subtotal - $valorVenta, 2);
            $valorUnitario = $cantidad > 0 ? round($valorVenta / $cantidad, 5) : 0.0;

            return [
                'tipAfeIgv'         => (int) ($d->tip_afe_igv ?? '10'),
                'codProducto'       => $codProducto,
                'unidad'            => $d->unidad ?? 'NIU',
                'descripcion'       => $d->descripcion,
                'cantidad'          => $cantidad,
                'mtoValorUnitario'  => $valorUnitario,
                'mtoPrecioUnitario' => $precioUnit,
                'mtoValorVenta'     => $valorVenta,
                'mtoBaseIgv'        => $valorVenta,
                'porcentajeIgv'     => $igvPorcentaje,
                'igv'               => $igv,
                'totalImpuestos'    => $igv,
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

        $estado = in_array($estadoSunat, [EstadoSunat::DadoDeBaja, EstadoSunat::PorDarBaja], strict: true) ? '3' : '1';

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
        // Los endpoints de resumen/baja llaman a SUNAT de forma asíncrona;
        // SUNAT puede tardar hasta 90 s antes de devolver el ticket o CDR.
        $timeout = str_contains($endpoint, 'summar') || str_contains($endpoint, 'void')
            ? 90
            : 30;

        try {
            $url      = rtrim($config->facturador_url, '/') . $endpoint;
            $response = Http::withToken($config->facturador_api_token)
                ->timeout($timeout)
                ->acceptJson()
                ->post($url, $body);

            $json = $response->json();

            if (! $response->successful() || ($json !== null && ! ($json['success'] ?? true))) {
                Log::warning('FacturadorService: respuesta no exitosa o success=false', [
                    'endpoint' => $endpoint,
                    'status'   => $response->status(),
                    'json'     => $json,
                    'raw'      => $json === null ? $response->body() : null,
                ]);
            }

            return $json;
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
     * Sincroniza los datos de la empresa (RUC, nombre, dirección, cert, SOL)
     * en el FacturadorGreenter usando el endpoint POST /api/my-company/update.
     * Se llama automáticamente al guardar datos de empresa o credenciales FE.
     */
    public function sincronizarEmpresa(Empresa $empresa): FacturadorResponse
    {
        $empresa->loadMissing('facturacion');
        $config = $empresa->facturacion;

        if (! $config?->facturador_url || ! $config?->facturador_api_token) {
            return FacturadorResponse::fromError('Empresa sin URL o token del facturador configurados.');
        }

        $url = rtrim($config->facturador_url, '/') . '/api/my-company/update';

        $payload = array_filter([
            'razon_social'  => $empresa->name,
            'ruc'           => $empresa->ruc,
            'direccion'     => $empresa->direccion,
            'departamento'  => $empresa->departamento,
            'provincia'     => $empresa->provincia,
            'distrito'      => $empresa->distrito,
            'ubigeo'        => $empresa->ubigeo,
            'telefono'      => $empresa->telefono,
            'email'         => $empresa->email,
            'sol_user'      => $config->sol_user,
            'sol_pass'      => $config->sol_pass, // modelo lo desencripta automáticamente
        ], fn($v) => $v !== null && $v !== '');

        try {
            $request = Http::withToken($config->facturador_api_token)->timeout(30)->acceptJson();

            if ($config->cert_path && Storage::disk('local')->exists($config->cert_path)) {
                $certContent = Storage::disk('local')->get($config->cert_path);
                $request     = $request->attach('cert', $certContent, 'certificado.pem');
            }

            $response = $request->post($url, $payload);

            if ($response->successful()) {
                return new FacturadorResponse(ok: true);
            }

            $json = $response->json() ?? [];
            return FacturadorResponse::fromError($json['message'] ?? "Error HTTP {$response->status()} al sincronizar empresa.");
        } catch (\Throwable $e) {
            Log::error('FacturadorService::sincronizarEmpresa', [
                'empresa_id' => $empresa->id,
                'error'      => $e->getMessage(),
            ]);
            return FacturadorResponse::fromError($e->getMessage());
        }
    }

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

        $igvPct = (float) ($empresa->igv_porcentaje ?? 18);

        // Descuento distribuido proporcionalmente en precios: ratio = total_pagado / total_original
        $totalOriginal = round(
            $venta->detalles->sum(fn($d) => (float) $d->precio_unitario * (float) $d->cantidad),
            2
        );
        $totalPagado   = (float) ($venta->total ?? 0);
        $discountRatio = ($totalOriginal > 0 && $totalPagado < $totalOriginal)
            ? $totalPagado / $totalOriginal
            : 1.0;

        $details = $this->buildDetails($venta->detalles, $igvPct, $discountRatio);

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
            'details'       => $details,
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

        $tipoDoc = $nota->tipo->tipoDocSunat();

        $numDocAfectado = $ventaOriginal->serie->serie . '-'
            . str_pad((string) $ventaOriginal->correlativo, 8, '0', STR_PAD_LEFT);

        $details = $this->buildDetails(
            $ventaOriginal->detalles,
            (float) ($empresa->igv_porcentaje ?? 18)
        );

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
            'details'        => $details,
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

        // El correlativo almacenado es "RC-YYYYMMDD-NNN"; la API solo espera el número ("NNN")
        $corrNum = last(explode('-', $resumen->correlativo));

        // fechaResumen  → cbc:IssueDate       (fecha de generación del RC = hoy)
        // fechaGeneracion → cbc:ReferenceDate  (fecha de las boletas referenciadas)
        // Para RCs normales ambas son hoy; para RCs de baja la referencia es la fecha de emisión
        // de la boleta (que puede ser del día anterior), pero el IssueDate siempre debe ser hoy.
        $body = [
            'company'         => ['ruc' => $empresa->ruc],
            'fechaGeneracion' => $resumen->fecha_referencia->format('Y-m-d'),
            'fechaResumen'    => now()->format('Y-m-d'),
            'correlativo'     => $corrNum,
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

        // El correlativo almacenado es "RA-YYYYMMDD-NNN"; la API solo espera el número ("NNN")
        $corrNum = last(explode('-', $resumen->correlativo));

        $body = [
            'correlativo'       => $corrNum,
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
     * Construye el ítem del details[] para RC de baja de una nota (NC/ND de boleta).
     * Los totales financieros se toman de la venta original (mismo importe que la nota).
     */
    private function buildSummaryDetailNota(Nota $nota): array
    {
        $numNota = $nota->serie->serie . '-' . str_pad((string) $nota->correlativo, 8, '0', STR_PAD_LEFT);
        $venta   = $nota->venta;

        return [
            'tipoDoc'            => $nota->tipo->tipoDocSunat(),
            'serieNro'           => $numNota,
            'estado'             => '3',
            'clienteTipo'        => $this->mapTipoDocCliente($venta->cliente_tipo_doc),
            'clienteNro'         => $venta->cliente_num_doc ?? '-',
            'total'              => (float) $nota->total,
            'mtoOperGravadas'    => (float) ($venta->op_gravadas    ?? 0),
            'mtoOperExoneradas'  => (float) ($venta->op_exoneradas  ?? 0),
            'mtoOperInafectas'   => (float) ($venta->op_inafectas   ?? 0),
            'mtoOperExportacion' => 0.0,
            'mtoOtrosCargos'     => 0.0,
            'porcentajeIgv'      => 18.0,
            'mtoIGV'             => (float) ($venta->igv            ?? 0),
        ];
    }

    /**
     * Envía un RC de baja para notas de crédito/débito sobre boletas (tipoDoc 07/08).
     * Se usa cuando la nota referencia una boleta (serie empieza con 'B').
     */
    public function enviarResumenNotas(ResumenSunat $resumen, Collection $notas): FacturadorResponse
    {
        $resumen->loadMissing('empresa.facturacion');
        $notas->loadMissing(['serie', 'venta']);

        $empresa = $resumen->empresa;
        $config  = $empresa?->facturacion;

        if (! $config) {
            return FacturadorResponse::fromError('Empresa sin configuración de facturación.');
        }

        $corrNum = last(explode('-', $resumen->correlativo));

        $body = [
            'company'         => ['ruc' => $empresa->ruc],
            'fechaGeneracion' => $resumen->fecha_referencia->format('Y-m-d'),
            'fechaResumen'    => now()->format('Y-m-d'),
            'correlativo'     => $corrNum,
            'details'         => $notas->map(fn (Nota $n) => $this->buildSummaryDetailNota($n))->values()->all(),
        ];

        $json = $this->http($config, '/api/summaries/send', $body);
        if ($json === null) {
            return FacturadorResponse::fromError('Error de conexión con el facturador.');
        }

        return FacturadorResponse::fromSummaryEnvio($json);
    }

    /**
     * Envía una Comunicación de Baja (RA) para notas de crédito/débito sobre facturas.
     * Se usa cuando la nota referencia una factura (serie empieza con 'F').
     */
    public function enviarBajaNota(ResumenSunat $resumen, Collection $notas, string $motivo = 'Error en emisión'): FacturadorResponse
    {
        $resumen->loadMissing('empresa.facturacion');
        $notas->loadMissing('serie');

        $empresa = $resumen->empresa;
        $config  = $empresa?->facturacion;

        if (! $config) {
            return FacturadorResponse::fromError('Empresa sin configuración de facturación.');
        }

        $details = $notas->map(function (Nota $nota) use ($motivo): array {
            return [
                'tipoDoc'    => $nota->tipo->tipoDocSunat(),
                'serie'      => $nota->serie->serie,
                'correlativo'=> str_pad((string) $nota->correlativo, 8, '0', STR_PAD_LEFT),
                'motivo'     => $motivo,
            ];
        })->values()->all();

        $corrNum = last(explode('-', $resumen->correlativo));

        $body = [
            'correlativo'       => $corrNum,
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

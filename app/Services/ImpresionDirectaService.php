<?php

namespace App\Services;

use App\Events\PrintComandaJob;
use App\Events\PrintComprobanteJob;
use App\Models\Empresa;
use App\Models\Orden;
use App\Models\SesionCaja;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class ImpresionDirectaService
{
    // =========================================================================
    // COMPROBANTE DE PAGO
    // Valida: impresion_comprobante_directo activo + caja con impresora
    // Genera PDF en base64 y emite PrintComprobanteJob
    // =========================================================================
    public function imprimirComprobante(Venta $venta, Empresa $empresa): bool
    {
        $config = $empresa->cachedConfigImpresion();

        if (! $config['tiene_impresion_directa']) {
            return false;
        }

        if (! $config['impresion_comprobante_directo']) {
            return false;
        }

        $apiToken = $config['api_token_impresion'];
        if (! $apiToken) {
            return false;
        }

        // Buscar sesión de caja activa del usuario actual con su impresora
        $sesionCaja = SesionCaja::where('empresa_id', $empresa->id)
            ->where('user_id', Auth::id())
            ->where('estado', \App\Enums\EstadoSesion::Abierta->value)
            ->with('caja.impresora')
            ->latest()
            ->first();

        $nombreImpresora = $sesionCaja?->caja?->impresora?->nombre ?? null;

        // Generar PDF en base64 usando el servicio existente
        $pdfService = app(PdfVentaService::class);
        $pdf        = $pdfService->generar($venta, $empresa);
        $base64     = base64_encode($pdf->output());

        $cajeroNombre = Auth::user()?->name ?? 'Sistema';
        $numero       = ($venta->serie?->serie ?? '---') . '-'
            . str_pad($venta->correlativo, 8, '0', STR_PAD_LEFT);

        event(new PrintComprobanteJob([
            'tipo'           => 'comprobante',
            'api_token'      => $apiToken,
            'pdf_base64'     => $base64,
            'printer_name'   => $nombreImpresora,   // nombre de impresora (o null = predeterminada)
            'numero'         => $numero,
            'total'          => (float) $venta->total,
            'cajero'         => $cajeroNombre,
            'hora'           => now()->format('H:i:s'),
            'fecha'          => now()->format('d/m/Y'),
        ]));

        return true;
    }

    // =========================================================================
    // COMANDA DE ORDEN DE RESTAURANTE (ítems no enviados → agrupados por área)
    // =========================================================================
    public function imprimirComandaOrden(Orden $orden, Empresa $empresa): void
    {
        $config = $empresa->cachedConfigImpresion();

        if (! $config['tiene_impresion_directa']) {
            return;
        }

        $apiToken = $config['api_token_impresion'];
        if (! $apiToken) {
            return;
        }

        $orden->loadMissing(['detalles.producto.produccion.impresora', 'mesa.piso']);

        $sinEnviar = $orden->detalles->where('enviado_cocina', false);

        if ($sinEnviar->isEmpty()) {
            return;
        }

        $cajeroNombre = Auth::user()?->name ?? 'Sistema';
        $mesaNombre   = $orden->mesa?->nombre;
        $pisoNombre   = $orden->mesa?->piso?->nombre;
        $esParcial    = $orden->detalles->where('enviado_cocina', true)->isNotEmpty();

        $itemsPorArea = [];
        foreach ($sinEnviar as $detalle) {
            $produccion = $detalle->producto?->produccion ?? null;
            $impresora  = $produccion?->impresora ?? null;

            if (! $produccion || ! $impresora || ! $impresora->estado) {
                continue;
            }

            $areaKey = 'area_' . $produccion->id;
            $itemsPorArea[$areaKey]['nombre']       = $produccion->nombre;
            $itemsPorArea[$areaKey]['printer_name'] = $impresora->nombre;
            $itemsPorArea[$areaKey]['es_parcial']   = $esParcial;
            $itemsPorArea[$areaKey]['mesa_nombre']  = $mesaNombre;
            $itemsPorArea[$areaKey]['piso_nombre']  = $pisoNombre;
            $itemsPorArea[$areaKey]['cancelados']   = $itemsPorArea[$areaKey]['cancelados'] ?? [];
            $itemsPorArea[$areaKey]['nuevos'][]     = [
                'cant'   => (int) $detalle->cantidad,
                'nombre' => $detalle->descripcion ?? $detalle->producto?->nombre ?? '—',
                'nota'   => $detalle->notas_item ?? null,
            ];
        }

        foreach ($itemsPorArea as $areaData) {
            $base64 = $this->generarBase64Comanda($areaData, $cajeroNombre);

            event(new PrintComandaJob([
                'tipo'         => 'comanda',
                'api_token'    => $apiToken,
                'pdf_base64'   => $base64,
                'printer_name' => $areaData['printer_name'],
                'area'         => $areaData['nombre'],
                'cajero'       => $cajeroNombre,
                'hora'         => now()->format('H:i'),
                'fecha'        => now()->format('d/m/Y'),
            ]));
        }
    }

    // =========================================================================
    // PRODUCTOS ELIMINADOS de un pedido (envía solo items cancelados)
    // =========================================================================
    public function imprimirEliminados(Orden $orden, Empresa $empresa, array $eliminados): void
    {
        if (empty($eliminados)) {
            return;
        }

        $config = $empresa->cachedConfigImpresion();

        if (! $config['tiene_impresion_directa']) {
            return;
        }

        $apiToken = $config['api_token_impresion'];
        if (! $apiToken) {
            return;
        }

        $orden->loadMissing(['detalles.producto.produccion.impresora', 'mesa.piso']);

        $cajeroNombre = Auth::user()?->name ?? 'Sistema';
        $mesaNombre   = $orden->mesa?->nombre;
        $pisoNombre   = $orden->mesa?->piso?->nombre;

        // Agrupar eliminados por las áreas de producción de los productos que aún quedan en la orden
        // Como no sabemos a qué área pertenecía el item eliminado, usamos las áreas del pedido activo
        // Si hay impresora de piso, enviar ahí; si no, a todas las áreas activas del pedido
        $areasPedido = [];
        foreach ($orden->detalles as $detalle) {
            $produccion = $detalle->producto?->produccion ?? null;
            $impresora  = $produccion?->impresora ?? null;
            if (! $produccion || ! $impresora || ! $impresora->estado) {
                continue;
            }
            $areaKey = 'area_' . $produccion->id;
            $areasPedido[$areaKey] = [
                'nombre'       => $produccion->nombre,
                'printer_name' => $impresora->nombre,
            ];
        }

        // Si no hay áreas de producción con impresora, usar la impresora del piso
        if (empty($areasPedido)) {
            $impresora = $orden->mesa?->piso?->impresora;
            if ($impresora && $impresora->estado) {
                $areasPedido['piso'] = [
                    'nombre'       => $orden->mesa?->piso?->nombre ?? 'Cocina',
                    'printer_name' => $impresora->nombre,
                ];
            }
        }

        $canceladosNormalizados = array_map(fn ($e) => [
            'cant'   => (int) ($e['cant'] ?? $e['cantidad'] ?? 0),
            'nombre' => $e['nombre'] ?? '—',
            'nota'   => $e['nota'] ?? '',
        ], $eliminados);

        foreach ($areasPedido as $areaData) {
            $areaDataCompleta = array_merge($areaData, [
                'es_parcial'   => true,
                'mesa_nombre'  => $mesaNombre,
                'piso_nombre'  => $pisoNombre,
                'nuevos'       => [],
                'cancelados'   => $canceladosNormalizados,
            ]);
            $base64 = $this->generarBase64Comanda($areaDataCompleta, $cajeroNombre);

            event(new PrintComandaJob([
                'tipo'         => 'comanda',
                'api_token'    => $apiToken,
                'pdf_base64'   => $base64,
                'printer_name' => $areaData['printer_name'],
                'area'         => $areaData['nombre'],
                'cajero'       => $cajeroNombre,
                'hora'         => now()->format('H:i'),
                'fecha'        => now()->format('d/m/Y'),
            ]));
        }
    }

    // =========================================================================
    // PRE-CUENTA DE MESA (envía a la impresora del piso)
    // =========================================================================
    public function imprimirPreCuentaMesa(Orden $orden, Empresa $empresa): bool
    {
        $config = $empresa->cachedConfigImpresion();

        if (! $config['tiene_impresion_directa']) {
            return false;
        }

        $apiToken = $config['api_token_impresion'];
        if (! $apiToken) {
            return false;
        }

        $orden->loadMissing(['detalles.producto', 'mesa.piso.impresora']);

        $impresora = $orden->mesa?->piso?->impresora;
        if (! $impresora || ! $impresora->estado) {
            return false;
        }

        $cajeroNombre = Auth::user()?->name ?? 'Sistema';

        $pdf = Pdf::loadView('pdv.ticket-precuenta-pdf', [
            'orden'        => $orden,
            'cajeroNombre' => $cajeroNombre,
            'empresa'      => $empresa,
        ])
            ->setPaper([0, 0, 226.77, 800], 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'Courier');

        $base64 = base64_encode($pdf->output());

        event(new PrintComandaJob([
            'tipo'         => 'precuenta',
            'api_token'    => $apiToken,
            'pdf_base64'   => $base64,
            'printer_name' => $impresora->nombre,
            'area'         => 'PRECUENTA',
            'cajero'       => $cajeroNombre,
            'hora'         => now()->format('H:i'),
            'fecha'        => now()->format('d/m/Y'),
        ]));

        return true;
    }

    // =========================================================================
    // COMANDAS POR ÁREA DE PRODUCCIÓN
    // Agrupa los ítems de la venta por produccion → valida que tenga impresora
    // activa → emite un PrintComandaJob por cada área
    // =========================================================================
    public function imprimirComandas(Venta $venta, Empresa $empresa, array $itemsPorArea = []): void
    {
        $config = $empresa->cachedConfigImpresion();

        if (! $config['tiene_impresion_directa']) {
            return;
        }

        // Reutiliza el api_token de la empresa (mismo canal raíz)
        $apiToken = $config['api_token_impresion'];
        if (! $apiToken) {
            return;
        }

        // Si no se pasaron ítems pre-agrupados, los construimos desde la venta
        if (empty($itemsPorArea)) {
            $itemsPorArea = $this->agruparPorArea($venta);
        }

        if (empty($itemsPorArea)) {
            return;
        }

        $cajeroNombre = Auth::user()?->name ?? 'Sistema';

        foreach ($itemsPorArea as $areaData) {
            // Solo emitir si el área tiene impresora configurada y está activa
            if (empty($areaData['printer_name'])) {
                continue;
            }

            $base64 = $this->generarBase64Comanda($areaData, $cajeroNombre);

            event(new PrintComandaJob([
                'tipo'         => 'comanda',
                'api_token'    => $apiToken,
                'pdf_base64'   => $base64,
                'printer_name' => $areaData['printer_name'],
                'area'         => $areaData['nombre'],
                'cajero'       => $cajeroNombre,
                'hora'         => now()->format('H:i'),
                'fecha'        => now()->format('d/m/Y'),
            ]));
        }
    }

    // =========================================================================
    // Agrupa los detalles de la venta por área de producción.
    // Solo incluye áreas que tienen una impresora activa asignada.
    // =========================================================================
    private function agruparPorArea(Venta $venta): array
    {
        $venta->loadMissing(['detalles.producto.produccion.impresora']);

        $itemsPorArea = [];

        foreach ($venta->detalles as $detalle) {
            $produccion = $detalle->producto?->produccion ?? null;
            $impresora  = $produccion?->impresora ?? null;

            // Ignorar si el área no tiene impresora activa
            if (! $produccion || ! $impresora || ! $impresora->estado) {
                continue;
            }

            $areaKey = 'area_' . $produccion->id;

            $itemsPorArea[$areaKey]['nombre']       = $produccion->nombre;
            $itemsPorArea[$areaKey]['printer_name'] = $impresora->nombre;
            $itemsPorArea[$areaKey]['es_parcial']   = false;
            $itemsPorArea[$areaKey]['nuevos'][]     = [
                'cant'   => $detalle->cantidad,
                'nombre' => $detalle->nombre_producto ?? $detalle->producto?->nombre ?? '—',
                'nota'   => $detalle->nota ?? null,
            ];
            $itemsPorArea[$areaKey]['cancelados']   = $itemsPorArea[$areaKey]['cancelados'] ?? [];
        }

        return $itemsPorArea;
    }

    // =========================================================================
    // Genera el PDF de una comanda en base64
    // =========================================================================
    private function generarBase64Comanda(array $areaData, string $cajeroNombre): string
    {
        $pdf = Pdf::loadView('pdv.ticket-comanda-pdf', [
            'itemsParaImprimir' => [
                'nuevos'     => $areaData['nuevos']     ?? [],
                'cancelados' => $areaData['cancelados'] ?? [],
            ],
            'esParcial'    => $areaData['es_parcial'] ?? false,
            'areaNombre'   => $areaData['nombre'],
            'cajeroNombre' => $cajeroNombre,
            'mesaNombre'   => $areaData['mesa_nombre'] ?? null,
            'pisoNombre'   => $areaData['piso_nombre'] ?? null,
        ])
            ->setPaper([0, 0, 226.77, 600], 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'Courier');

        return base64_encode($pdf->output());
    }
}

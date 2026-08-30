<?php

namespace App\Services;

use App\Events\PrintComandaJob;
use App\Events\PrintComprobanteJob;
use App\Models\Empresa;
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
            'esParcial'   => $areaData['es_parcial'] ?? false,
            'areaNombre'  => $areaData['nombre'],
            'cajeroNombre'=> $cajeroNombre,
        ])
            ->setPaper([0, 0, 226.77, 600], 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'Courier');

        return base64_encode($pdf->output());
    }
}

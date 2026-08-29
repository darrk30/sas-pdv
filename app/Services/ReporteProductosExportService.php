<?php

namespace App\Services;

use App\Models\Empresa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteProductosExportService
{
    private const COLS = [
        'descripcion' => ['Producto',    'descripcion'],
        'categoria'   => ['Categoría',   'categoria'],
        'qty'         => ['Unidades',    'qty'],
        'ingresos'    => ['Ventas',      'ingresos'],
        'costo'       => ['Costo',       'costo'],
        'utilidad'    => ['Utilidad',    'utilidad'],
        'margen'      => ['Margen %',    'margen'],
    ];

    private const MONEY_COLS = ['ingresos', 'costo', 'utilidad'];

    public function getActiveColumns(array $columnasVisibles): array
    {
        $active   = [];
        $fallback = empty($columnasVisibles);

        foreach (self::COLS as $key => [$label, $toggleKey]) {
            if ($fallback || in_array($toggleKey, $columnasVisibles)) {
                $active[$key] = $label;
            }
        }

        return $active;
    }

    private function valor(object $row, string $key): mixed
    {
        $ingresos = (float) $row->ingresos;

        return match ($key) {
            'descripcion' => $row->descripcion ?? '',
            'categoria'   => $row->categoria ?? '',
            'qty'         => number_format((float) $row->qty, 2) . ' ' . ($row->unidad ?? 'und'),
            'ingresos'    => $ingresos,
            'costo'       => (float) $row->costo,
            'utilidad'    => (float) $row->utilidad,
            'margen'      => $ingresos > 0
                ? round((float) $row->utilidad / $ingresos * 100, 1)
                : '',
            default       => '',
        };
    }

    public function generarPdf(
        Collection $productos,
        array $filtrosInfo,
        array $columnasVisibles,
        array $resumen,
        Empresa $empresa
    ): StreamedResponse {
        $activeColumns = $this->getActiveColumns($columnasVisibles);
        $nombre        = 'reporte-productos-' . now()->format('Ymd-His') . '.pdf';
        $usuarioNombre = auth()->user()?->name ?? 'Sistema';

        $rows = $productos->map(function ($row) use ($activeColumns): array {
            $r = [];
            foreach (array_keys($activeColumns) as $key) {
                $r[$key] = $this->valor($row, $key);
            }
            return $r;
        });

        $totales = [
            'ingresos' => (float) $productos->sum('ingresos'),
            'costo'    => (float) $productos->sum('costo'),
            'utilidad' => (float) $productos->sum('utilidad'),
        ];

        $moneyKeys = self::MONEY_COLS;

        $pdf = Pdf::loadView('reports.reporte-productos-pdf', compact(
            'productos', 'rows', 'filtrosInfo', 'activeColumns',
            'resumen', 'empresa', 'totales', 'moneyKeys', 'usuarioNombre'
        ))->setPaper('a4', 'landscape')
        ->setOption([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => false,
            'defaultFont'          => 'DejaVu Sans',
        ]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $nombre, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }
}

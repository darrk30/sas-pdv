<?php

namespace App\Services;

use App\Enums\EstadoVenta;
use App\Models\Empresa;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteGananciasExportService
{
    // key => [label, filament_column_name|null]
    private const COLS = [
        'comprobante'    => ['Comprobante',    'comprobante'],
        'created_at'     => ['Fecha',           'created_at'],
        'cliente_nombre' => ['Cliente',         'cliente_nombre'],
        'vendedor'       => ['Vendedor',        'cliente_nombre'], // sigue toggle de cliente
        'estado_pago'    => ['Pago',            'estado_pago'],
        'total'          => ['Total',           'total'],
        'venta_neta'     => ['Venta neta',      'venta_neta'],
        'costo_total'    => ['Costo',           'costo_total'],
        'utilidad'       => ['Utilidad',        'utilidad'],
        'utilidad_riesgo'=> ['En riesgo',       'utilidad_riesgo'],
        'saldo_pendiente'=> ['Saldo pendiente', 'saldo_pendiente'],
        'margen'         => ['Margen %',        'margen'],
    ];

    private const MONEY_COLS = ['total', 'venta_neta', 'costo_total', 'utilidad', 'utilidad_riesgo', 'saldo_pendiente'];

    // ── Utilidad por fila (misma lógica que la página) ────────────────────────

    private static function calcularUtilidad(Venta $v): array
    {
        $ventaNeta     = (float) $v->total - (float) $v->igv;
        $costo         = (float) $v->costo_total;
        $utilidadTotal = $ventaNeta - $costo;
        $total         = (float) $v->total;
        $estadoPago    = $v->estado_pago ?? 'pagado';

        if ($estadoPago === 'pagado' || $total <= 0) {
            return ['cobrada' => $utilidadTotal, 'riesgo' => 0.0, 'neta' => $ventaNeta];
        }

        $pctCobrado      = (float) $v->monto_pagado / $total;
        $utilidadCobrada = $utilidadTotal * $pctCobrado;

        return [
            'cobrada' => $utilidadCobrada,
            'riesgo'  => $utilidadTotal - $utilidadCobrada,
            'neta'    => $ventaNeta,
        ];
    }

    // ── Columnas activas ──────────────────────────────────────────────────────

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

    // ── Valor de celda ────────────────────────────────────────────────────────

    private function valor(Venta $v, string $key): mixed
    {
        $u = self::calcularUtilidad($v);

        return match ($key) {
            'comprobante'     => ($v->serie?->serie ?? '---') . '-' . str_pad((string) $v->correlativo, 8, '0', STR_PAD_LEFT),
            'created_at'      => $v->created_at?->format('d/m/Y H:i') ?? '',
            'cliente_nombre'  => $v->cliente_nombre ?? '',
            'vendedor'        => $v->vendedor?->name ?? '—',
            'estado_pago'     => match ($v->estado_pago ?? 'pagado') {
                'pendiente' => 'Crédito',
                'parcial'   => 'Parcial',
                default     => 'Contado',
            },
            'total'           => (float) $v->total,
            'venta_neta'      => $u['neta'],
            'costo_total'     => (float) $v->costo_total,
            'utilidad'        => $u['cobrada'],
            'utilidad_riesgo' => $u['riesgo'],
            'saldo_pendiente' => (float) $v->saldo_pendiente,
            'margen'          => $u['neta'] > 0 && ($v->estado_pago ?? 'pagado') !== 'pendiente'
                ? round($u['cobrada'] / $u['neta'] * 100, 1)
                : '',
            default           => '',
        };
    }

    // ── Excel ─────────────────────────────────────────────────────────────────

    public function generarExcel(
        Collection $ventas,
        array $filtrosInfo,
        array $columnasVisibles,
        array $resumen,
        Empresa $empresa
    ): StreamedResponse {
        $spreadsheet = $this->buildSpreadsheet($ventas, $filtrosInfo, $columnasVisibles, $resumen, $empresa);
        $nombre      = 'reporte-ganancias-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $nombre, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    private function buildSpreadsheet(
        Collection $ventas,
        array $filtrosInfo,
        array $columnasVisibles,
        array $resumen,
        Empresa $empresa
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet()->setTitle('Ganancias');

        $activeColumns = $this->getActiveColumns($columnasVisibles);
        $colCount      = count($activeColumns);
        $lastCol       = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        $colorHeader  = '1E3A5F';
        $colorSubhead = '2C5282';
        $colorAlt     = 'EBF0F8';
        $colorTotal   = 'FFF3CD';
        $white        = 'FFFFFF';

        $row     = 1;
        $usuario = auth()->user()?->name ?? 'Sistema';

        // Empresa
        $sheet->setCellValue("A{$row}", strtoupper($empresa->nombre));
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => $white]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorHeader]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        // RUC
        $sheet->setCellValue("A{$row}", 'RUC ' . ($empresa->ruc ?? ''));
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['size' => 10, 'color' => ['argb' => $white]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorHeader]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;

        // Título reporte
        $sheet->setCellValue("A{$row}", 'REPORTE DE GANANCIAS');
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['argb' => $white]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorSubhead]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        // Generado por
        $sheet->setCellValue("A{$row}", 'Generado: ' . now()->format('d/m/Y H:i') . '  —  ' . $usuario);
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => $white]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorSubhead]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;
        $row++; // blank

        // Filtros
        if (! empty($filtrosInfo)) {
            $sheet->setCellValue("A{$row}", 'FILTROS APLICADOS');
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'DCE8F5']],
            ]);
            $row++;
            foreach ($filtrosInfo as $label => $valor) {
                $sheet->setCellValue("A{$row}", $label . ':');
                $sheet->setCellValue("B{$row}", $valor);
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                $sheet->mergeCells("B{$row}:{$lastCol}{$row}");
                $row++;
            }
            $row++;
        }

        // Encabezados
        $headerRow = $row;
        $colIdx    = 1;
        foreach ($activeColumns as $label) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx++);
            $sheet->setCellValue("{$col}{$row}", strtoupper($label));
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => $white], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorHeader]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;

        // Datos
        $isAlt = false;
        foreach ($ventas as $venta) {
            $colIdx = 1;
            foreach (array_keys($activeColumns) as $key) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx++);
                $val = $this->valor($venta, $key);
                $sheet->setCellValue("{$col}{$row}", $val);

                if (in_array($key, self::MONEY_COLS) && is_float($val)) {
                    $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode('"S/ "#,##0.00');
                    $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                if ($key === 'margen' && $val !== '') {
                    $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode('0.0"%"');
                    $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }
            if ($isAlt) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($colorAlt);
            }
            $isAlt = ! $isAlt;
            $row++;
        }

        // Fila totales
        $totalRow = $row;
        $colIdx   = 1;
        $totalesMoney = [
            'total'           => (float) $ventas->sum('total'),
            'venta_neta'      => (float) $ventas->sum(fn($v) => (float)$v->total - (float)$v->igv),
            'costo_total'     => (float) $ventas->sum('costo_total'),
            'utilidad'        => (float) $ventas->sum(fn($v) => self::calcularUtilidad($v)['cobrada']),
            'utilidad_riesgo' => (float) $ventas->sum(fn($v) => self::calcularUtilidad($v)['riesgo']),
            'saldo_pendiente' => (float) $ventas->sum('saldo_pendiente'),
        ];
        foreach (array_keys($activeColumns) as $key) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx++);
            if ($key === 'comprobante') {
                $sheet->setCellValue("{$col}{$totalRow}", 'TOTAL (' . $ventas->count() . ' registros)');
            } elseif (isset($totalesMoney[$key])) {
                $sheet->setCellValue("{$col}{$totalRow}", $totalesMoney[$key]);
                $sheet->getStyle("{$col}{$totalRow}")->getNumberFormat()->setFormatCode('"S/ "#,##0.00');
                $sheet->getStyle("{$col}{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }
        $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
            'font'    => ['bold' => true],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorTotal]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);
        $row += 2;

        // Resumen KPIs
        $sheet->setCellValue("A{$row}", 'RESUMEN');
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'DCE8F5']],
        ]);
        $row++;

        $kpis = [
            'Ventas completadas'  => $resumen['cantidad'],
            'Ingresos brutos'     => 'S/ ' . number_format($resumen['ingresosBrutos'], 2),
            'Ventas netas'        => 'S/ ' . number_format($resumen['ventasNetas'], 2),
            'Costo de ventas'     => 'S/ ' . number_format($resumen['costoTotal'], 2),
            'Utilidad cobrada'    => 'S/ ' . number_format($resumen['utilidadRealizada'], 2),
            'Margen bruto'        => number_format($resumen['margenPct'], 1) . '%',
        ];
        if (($resumen['creditoPendiente'] ?? 0) > 0) {
            $kpis['Crédito pendiente'] = 'S/ ' . number_format($resumen['creditoPendiente'], 2);
            $kpis['Utilidad en riesgo'] = 'S/ ' . number_format($resumen['utilidadEnRiesgo'], 2);
        }
        foreach ($kpis as $label => $valor) {
            $sheet->setCellValue("A{$row}", $label . ':');
            $sheet->setCellValue("B{$row}", $valor);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->mergeCells("B{$row}:{$lastCol}{$row}");
            $row++;
        }

        // Bordes y autosize
        if ($ventas->count() > 0) {
            $sheet->getStyle("A{$headerRow}:{$lastCol}{$totalRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'CCCCCC']]],
            ]);
        }
        foreach (range(1, $colCount) as $i) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        return $spreadsheet;
    }

    // ── PDF ───────────────────────────────────────────────────────────────────

    public function generarPdf(
        Collection $ventas,
        array $filtrosInfo,
        array $columnasVisibles,
        array $resumen,
        Empresa $empresa
    ): StreamedResponse {
        $activeColumns = $this->getActiveColumns($columnasVisibles);
        $nombre        = 'reporte-ganancias-' . now()->format('Ymd-His') . '.pdf';
        $usuarioNombre = auth()->user()?->name ?? 'Sistema';

        $rows = $ventas->map(function (Venta $v) use ($activeColumns): array {
            $row = [];
            foreach (array_keys($activeColumns) as $key) {
                $row[$key] = $this->valor($v, $key);
            }
            return $row;
        });

        $totales = [
            'total'           => (float) $ventas->sum('total'),
            'venta_neta'      => (float) $ventas->sum(fn($v) => (float)$v->total - (float)$v->igv),
            'costo_total'     => (float) $ventas->sum('costo_total'),
            'utilidad'        => (float) $ventas->sum(fn($v) => self::calcularUtilidad($v)['cobrada']),
            'utilidad_riesgo' => (float) $ventas->sum(fn($v) => self::calcularUtilidad($v)['riesgo']),
            'saldo_pendiente' => (float) $ventas->sum('saldo_pendiente'),
        ];

        $moneyKeys = self::MONEY_COLS;

        $pdf = Pdf::loadView('reports.reporte-ganancias-pdf', compact(
            'ventas', 'rows', 'filtrosInfo', 'activeColumns',
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

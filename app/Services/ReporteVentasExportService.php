<?php

namespace App\Services;

use App\Enums\CondicionPago;
use App\Models\Empresa;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteVentasExportService
{
    // key => [label, filament_column_name (usado para detectar visibilidad)]
    // 'cliente_doc' comparte el toggle de 'cliente_nombre' (es la descripción de ese campo)
    private const COLS = [
        'comprobante'       => ['Comprobante',   'comprobante'],
        'created_at'        => ['Fecha',          'created_at'],
        'cliente_nombre'    => ['Cliente',        'cliente_nombre'],
        'cliente_doc'       => ['Documento',      'cliente_nombre'],
        'detalles_count'    => ['Ítems',          'detalles_count'],
        'cortesias_count'   => ['Cortesía',       'cortesias_count'],
        'metodo'            => ['Método de Pago', 'metodo'],
        'op_gravadas'       => ['Op. Gravada',    'op_gravadas'],
        'igv'               => ['IGV',            'igv'],
        'descuento_total'   => ['Descuento',      'descuento_total'],
        'total'             => ['Total',          'total'],
        'estado'            => ['Estado',         'estado'],
        'estado_sunat'      => ['SUNAT',          'estado_sunat'],
        'notas_count'       => ['NC',             'notas_count'],
        'sunat_descripcion' => ['Desc. SUNAT',    'sunat_descripcion'],
    ];

    private const MONEY_COLS = ['op_gravadas', 'igv', 'descuento_total', 'total'];

    // ── Columnas activas según visibilidad de la tabla ────────────────────────

    public function getActiveColumns(array $columnasVisibles): array
    {
        $active   = [];
        $fallback = empty($columnasVisibles); // sin info de toggles → incluir todo

        foreach (self::COLS as $key => [$label, $toggleKey]) {
            if ($fallback || in_array($toggleKey, $columnasVisibles)) {
                $active[$key] = $label;
            }
        }

        return $active;
    }

    // ── Valor de celda para cada columna ─────────────────────────────────────

    private function valor(Venta $v, string $key): mixed
    {
        return match ($key) {
            'comprobante'       => ($v->serie?->serie ?? '---') . '-' . str_pad((string) $v->correlativo, 8, '0', STR_PAD_LEFT),
            'created_at'        => $v->created_at?->format('d/m/Y H:i') ?? '',
            'cliente_nombre'    => $v->cliente_nombre ?? '',
            'cliente_doc'       => strtoupper($v->cliente_tipo_doc ?? '') . ' ' . ($v->cliente_num_doc ?? ''),
            'detalles_count'    => (int) ($v->detalles_count ?? 0),
            'cortesias_count'   => ($v->cortesias_count ?? 0) > 0 ? 'Sí' : '—',
            'metodo'            => $v->pagos
                ->filter(fn($p) => $p->metodoPago?->condicion_pago !== CondicionPago::Credito || $v->estado_pago === 'pendiente')
                ->map(fn($p) => $p->metodoPago?->nombre)
                ->filter()->unique()->implode(', ') ?: '—',
            'op_gravadas'       => (float) ($v->op_gravadas ?? 0),
            'igv'               => (float) ($v->igv ?? 0),
            'descuento_total'   => (float) ($v->descuento_total ?? 0),
            'total'             => (float) ($v->total ?? 0),
            'estado'            => ucfirst($v->estado?->value ?? ''),
            'estado_sunat'      => $v->estado_sunat?->getLabel() ?? '—',
            'notas_count'       => (int) ($v->notas_count ?? 0),
            'sunat_descripcion' => $v->sunat_descripcion ?? '',
            default             => '',
        };
    }

    // ── Generar Excel ─────────────────────────────────────────────────────────

    public function generarExcel(
        Collection $ventas,
        array $filtrosInfo,
        array $columnasVisibles,
        array $resumen,
        Empresa $empresa
    ): StreamedResponse {
        $spreadsheet = $this->buildSpreadsheet($ventas, $filtrosInfo, $columnasVisibles, $resumen, $empresa);

        $nombre = 'reporte-ventas-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
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
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ventas');

        $activeColumns = $this->getActiveColumns($columnasVisibles);
        $colCount      = count($activeColumns);
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        // ── Paleta ────────────────────────────────────────────────────────────
        $colorHeader  = '1E3A5F';
        $colorSubhead = '2C5282';
        $colorAlt     = 'EBF0F8';
        $colorTotal   = 'FFF3CD';
        $colorText    = 'FFFFFF';

        $row     = 1;
        $usuario = auth()->user()?->name ?? 'Sistema';

        // ── Fila 1: Nombre de la empresa ──────────────────────────────────────
        $sheet->setCellValue("A{$row}", strtoupper($empresa->nombre));
        $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => $colorText]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorHeader]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        // ── Fila 2: RUC ───────────────────────────────────────────────────────
        $sheet->setCellValue("A{$row}", 'RUC ' . ($empresa->ruc ?? ''));
        $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['size' => 10, 'color' => ['argb' => $colorText]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorHeader]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;

        // ── Fila 3: Nombre del reporte ────────────────────────────────────────
        $sheet->setCellValue("A{$row}", 'REPORTE DE VENTAS');
        $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['argb' => $colorText]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorSubhead]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        // ── Fila 4: Generado por ──────────────────────────────────────────────
        $sheet->setCellValue("A{$row}", 'Generado: ' . now()->format('d/m/Y H:i') . '  —  ' . $usuario);
        $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => $colorText]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorSubhead]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;
        $row++; // blank

        // ── Filtros activos ───────────────────────────────────────────────────
        if (! empty($filtrosInfo)) {
            $sheet->setCellValue("A{$row}", 'FILTROS APLICADOS');
            $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 10],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'DCE8F5']],
            ]);
            $row++;

            foreach ($filtrosInfo as $label => $valor) {
                $sheet->setCellValue("A{$row}", $label . ':');
                $sheet->setCellValue("B{$row}", $valor);
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                $sheet->mergeCells("B{$row}:{$lastColLetter}{$row}");
                $row++;
            }
            $row++; // blank
        }

        // ── Encabezados de columnas ───────────────────────────────────────────
        $headerRow = $row;
        $colIdx    = 1;
        foreach ($activeColumns as $label) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue("{$colLetter}{$row}", strtoupper($label));
            $colIdx++;
        }
        $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => $colorText], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorHeader]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'AAAAAA']]],
        ]);
        $row++;

        // ── Filas de datos ────────────────────────────────────────────────────
        $dataStartRow = $row;
        $isAlt        = false;
        foreach ($ventas as $venta) {
            $colIdx = 1;
            foreach (array_keys($activeColumns) as $key) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                $val       = $this->valor($venta, $key);
                $sheet->setCellValue("{$colLetter}{$row}", $val);

                if (in_array($key, self::MONEY_COLS) && is_float($val)) {
                    $sheet->getStyle("{$colLetter}{$row}")
                        ->getNumberFormat()
                        ->setFormatCode('"S/ "#,##0.00');
                    $sheet->getStyle("{$colLetter}{$row}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $colIdx++;
            }

            if ($isAlt) {
                $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($colorAlt);
            }
            $isAlt = ! $isAlt;
            $row++;
        }

        // ── Fila de totales ───────────────────────────────────────────────────
        $totalRow  = $row;
        $colIdx    = 1;
        $totales   = [
            'op_gravadas'    => (float) $ventas->where('estado', \App\Enums\EstadoVenta::Completada)->sum('op_gravadas'),
            'igv'            => (float) $ventas->where('estado', \App\Enums\EstadoVenta::Completada)->sum('igv'),
            'descuento_total'=> (float) $ventas->sum('descuento_total'),
            'total'          => (float) $ventas->sum('total'),
        ];

        foreach (array_keys($activeColumns) as $key) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            if ($key === 'comprobante') {
                $sheet->setCellValue("{$colLetter}{$totalRow}", 'TOTAL (' . $ventas->count() . ' registros)');
            } elseif (isset($totales[$key])) {
                $sheet->setCellValue("{$colLetter}{$totalRow}", $totales[$key]);
                $sheet->getStyle("{$colLetter}{$totalRow}")
                    ->getNumberFormat()->setFormatCode('"S/ "#,##0.00');
                $sheet->getStyle("{$colLetter}{$totalRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $colIdx++;
        }
        $sheet->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorTotal]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);
        $row++;

        // ── Totales por método de pago ────────────────────────────────────────
        if (! empty($resumen['porMetodo'])) {
            $row++;
            $sheet->setCellValue("A{$row}", 'TOTALES POR MÉTODO DE PAGO');
            $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'DCE8F5']],
            ]);
            $row++;

            foreach ($resumen['porMetodo'] as $m) {
                $sheet->setCellValue("A{$row}", $m['nombre']);
                $sheet->setCellValue("B{$row}", (float) $m['total']);
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('"S/ "#,##0.00');
                $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $row++;
            }
        }

        // ── Auto-ancho de columnas ────────────────────────────────────────────
        foreach (range(1, $colCount) as $i) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Bordes en área de datos
        if ($ventas->count() > 0) {
            $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$totalRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'CCCCCC']],
                ],
            ]);
        }

        return $spreadsheet;
    }

    // ── Generar PDF ───────────────────────────────────────────────────────────

    public function generarPdf(
        Collection $ventas,
        array $filtrosInfo,
        array $columnasVisibles,
        array $resumen,
        Empresa $empresa
    ): StreamedResponse {
        $activeColumns = $this->getActiveColumns($columnasVisibles);
        $nombre        = 'reporte-ventas-' . now()->format('Ymd-His') . '.pdf';

        $rows = $ventas->map(function (Venta $v) use ($activeColumns) {
            $row = [];
            foreach (array_keys($activeColumns) as $key) {
                $row[$key] = $this->valor($v, $key);
            }
            return $row;
        });

        $totales = [
            'op_gravadas'     => (float) $ventas->where('estado', \App\Enums\EstadoVenta::Completada)->sum('op_gravadas'),
            'igv'             => (float) $ventas->where('estado', \App\Enums\EstadoVenta::Completada)->sum('igv'),
            'descuento_total' => (float) $ventas->sum('descuento_total'),
            'total'           => (float) $ventas->sum('total'),
        ];

        $moneyKeys = self::MONEY_COLS;

        $usuarioNombre = auth()->user()?->name ?? 'Sistema';

        $pdf = Pdf::loadView('reports.reporte-ventas-pdf', compact(
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

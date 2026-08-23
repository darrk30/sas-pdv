<?php

namespace App\Services;

use App\Models\Empresa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteVendedorVentasExportService
{
    private const COLS = [
        'comprobante'     => 'Comprobante',
        'created_at'      => 'Fecha',
        'cliente'         => 'Cliente',
        'estado_pago'     => 'Pago',
        'total'           => 'Total',
        'monto_pagado'    => 'Pagado',
        'saldo_pendiente' => 'Saldo pend.',
        'igv'             => 'IGV',
        'costo_total'     => 'Costo',
        'utilidad'        => 'Utilidad',
    ];

    private const MONEY_COLS = ['total', 'monto_pagado', 'saldo_pendiente', 'igv', 'costo_total', 'utilidad'];

    private function valor(array $r, string $key): mixed
    {
        return match($key) {
            'comprobante' => ($r['serie'] ?? '') . '-' . ($r['correlativo'] ?? ''),
            'created_at'  => $r['created_at']
                ? \Carbon\Carbon::parse($r['created_at'])->format('d/m/Y H:i')
                : '—',
            'cliente'     => $r['cliente_nombre'] ?: '—',
            'estado_pago' => ($r['estado_pago'] ?? '') === 'pendiente' ? 'Crédito' : 'Contado',
            'total'           => (float) ($r['total']           ?? 0),
            'monto_pagado'    => (float) ($r['monto_pagado']    ?? 0),
            'saldo_pendiente' => (float) ($r['saldo_pendiente'] ?? 0),
            'igv'             => (float) ($r['igv']             ?? 0),
            'costo_total'     => (float) ($r['costo_total']     ?? 0),
            'utilidad'        => (float) ($r['utilidad']        ?? 0),
            default       => '',
        };
    }

    // ── Excel ─────────────────────────────────────────────────────────────────

    public function generarExcel(
        Collection $ventas,
        array $filtrosInfo,
        array $resumen,
        Empresa $empresa,
        string $vendedorNombre
    ): StreamedResponse {
        $spreadsheet = $this->buildSpreadsheet($ventas, $filtrosInfo, $resumen, $empresa, $vendedorNombre);
        $slug        = \Illuminate\Support\Str::slug($vendedorNombre);
        $nombre      = 'ventas-' . $slug . '-' . now()->format('Ymd-His') . '.xlsx';

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
        array $resumen,
        Empresa $empresa,
        string $vendedorNombre
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet()->setTitle('Ventas');

        $cols     = self::COLS;
        $colCount = count($cols);
        $lastCol  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        $colorHeader  = '1E3A5F';
        $colorSubhead = '2C5282';
        $colorAlt     = 'EBF0F8';
        $colorTotal   = 'FFF3CD';
        $white        = 'FFFFFF';

        $row     = 1;
        $usuario = auth()->user()?->name ?? 'Sistema';

        foreach ([
            [strtoupper($empresa->nombre), 13, true, $colorHeader],
            ['RUC ' . ($empresa->ruc ?? ''), 10, false, $colorHeader],
            ['VENTAS — ' . strtoupper($vendedorNombre), 14, true, $colorSubhead],
            ['Generado: ' . now()->format('d/m/Y H:i') . '  —  ' . $usuario, 9, false, $colorSubhead],
        ] as [$texto, $size, $bold, $bg]) {
            $sheet->setCellValue("A{$row}", $texto);
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font'      => ['bold' => $bold, 'size' => $size, 'color' => ['argb' => $white], 'italic' => !$bold && $size === 9],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension($row)->setRowHeight($bold ? 20 : 16);
            $row++;
        }
        $row++;

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
        foreach ($cols as $label) {
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
        foreach ($ventas as $v) {
            $colIdx = 1;
            foreach (array_keys($cols) as $key) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx++);
                $val = $this->valor($v, $key);
                $sheet->setCellValue("{$col}{$row}", $val);

                if (in_array($key, self::MONEY_COLS) && is_float($val)) {
                    $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode('"S/ "#,##0.00');
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

        // Totales
        $totalRow = $row;
        $colIdx   = 1;
        foreach (array_keys($cols) as $key) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx++);
            if ($key === 'comprobante') {
                $sheet->setCellValue("{$col}{$totalRow}", 'TOTAL (' . $ventas->count() . ' ventas)');
            } elseif (in_array($key, self::MONEY_COLS)) {
                $sum = $ventas->sum(fn($r) => (float) ($r[$key] ?? 0));
                $sheet->setCellValue("{$col}{$totalRow}", $sum);
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

        // Resumen
        $sheet->setCellValue("A{$row}", 'RESUMEN');
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'DCE8F5']],
        ]);
        $row++;
        foreach ([
            'Total ventas'      => $resumen['cantidad'],
            'Total cobrado'     => 'S/ ' . number_format($resumen['cobrado'], 2),
            'Crédito pendiente' => 'S/ ' . number_format($resumen['creditoPendiente'], 2),
            'Utilidad'          => 'S/ ' . number_format($resumen['utilidad'], 2),
        ] as $label => $valor) {
            $sheet->setCellValue("A{$row}", $label . ':');
            $sheet->setCellValue("B{$row}", $valor);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->mergeCells("B{$row}:{$lastCol}{$row}");
            $row++;
        }

        // Bordes + autosize
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
        array $resumen,
        Empresa $empresa,
        string $vendedorNombre
    ): StreamedResponse {
        $slug          = \Illuminate\Support\Str::slug($vendedorNombre);
        $nombre        = 'ventas-' . $slug . '-' . now()->format('Ymd-His') . '.pdf';
        $usuarioNombre = auth()->user()?->name ?? 'Sistema';
        $moneyKeys     = self::MONEY_COLS;

        $keyedRows = $ventas->map(function ($r) {
            $row = [];
            foreach (array_keys(self::COLS) as $key) {
                $row[$key] = $this->valor($r, $key);
            }
            return $row;
        });

        $totales = [];
        foreach (self::MONEY_COLS as $key) {
            $totales[$key] = $ventas->sum(fn($r) => (float) ($r[$key] ?? 0));
        }

        $pdf = Pdf::loadView('reports.reporte-vendedor-ventas-pdf', [
            'ventas'          => $ventas,
            'rows'            => $keyedRows,
            'cols'            => self::COLS,
            'filtrosInfo'     => $filtrosInfo,
            'resumen'         => $resumen,
            'empresa'         => $empresa,
            'totales'         => $totales,
            'moneyKeys'       => $moneyKeys,
            'usuarioNombre'   => $usuarioNombre,
            'vendedorNombre'  => $vendedorNombre,
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $nombre, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }
}

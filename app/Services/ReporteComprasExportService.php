<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\Empresa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteComprasExportService
{
    private const COLS = [
        'comprobante'     => ['Comprobante',   'comprobante'],
        'tipo_comprobante'=> ['Tipo',          'comprobante'],   // sigue toggle de comprobante
        'fecha_compra'    => ['Fecha',         'fecha_compra'],
        'proveedor'       => ['Proveedor',     'proveedor.nombre'],
        'registrado_por'  => ['Registrado por','proveedor.nombre'], // sigue toggle de proveedor
        'estado'          => ['Estado',        'estado'],
        'estado_despacho' => ['Despacho',      'estado_despacho'],
        'estado_pago'     => ['Pago',          'estado_pago'],
        'total'           => ['Total',         'total'],
        'saldo'           => ['Saldo',         'saldo'],
    ];

    private const MONEY_COLS = ['total', 'saldo'];

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

    private function valor(Compra $c, string $key): mixed
    {
        $saldo = (float) $c->total - (float) ($c->pagos_sum_monto ?? 0);

        return match ($key) {
            'comprobante'     => ($c->serie && $c->correlativo)
                ? $c->serie . '-' . $c->correlativo
                : ($c->codigo ?? '—'),
            'tipo_comprobante'=> match ($c->tipo_comprobante) {
                'factura'         => 'Factura',
                'boleta'          => 'Boleta',
                'ticket'          => 'Ticket',
                'sin_comprobante' => 'Sin comprobante',
                default           => $c->tipo_comprobante,
            },
            'fecha_compra'    => $c->fecha_compra
                ? \Illuminate\Support\Carbon::parse($c->fecha_compra)->format('d/m/Y')
                : '—',
            'proveedor'       => $c->proveedor?->nombre ?? '—',
            'registrado_por'  => $c->registradoPor?->name ?? '—',
            'estado'          => match ($c->estado) {
                'borrador'   => 'Borrador',
                'confirmado' => 'Confirmado',
                'anulado'    => 'Anulado',
                default      => $c->estado,
            },
            'estado_despacho' => $c->estado_despacho === 'recibido' ? 'Recibido' : 'Pendiente',
            'estado_pago'     => $c->estado_pago === 'pagado' ? 'Pagado' : 'Pendiente',
            'total'           => (float) $c->total,
            'saldo'           => $saldo > 0.01 ? $saldo : 0.0,
            default           => '',
        };
    }

    // ── Excel ─────────────────────────────────────────────────────────────────

    public function generarExcel(
        Collection $compras,
        array $filtrosInfo,
        array $columnasVisibles,
        array $resumen,
        Empresa $empresa
    ): StreamedResponse {
        $spreadsheet = $this->buildSpreadsheet($compras, $filtrosInfo, $columnasVisibles, $resumen, $empresa);
        $nombre      = 'reporte-compras-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $nombre, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    private function buildSpreadsheet(
        Collection $compras,
        array $filtrosInfo,
        array $columnasVisibles,
        array $resumen,
        Empresa $empresa
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet()->setTitle('Compras');

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

        // Título
        $sheet->setCellValue("A{$row}", 'REPORTE DE COMPRAS');
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
        foreach ($compras as $compra) {
            $colIdx = 1;
            foreach (array_keys($activeColumns) as $key) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx++);
                $val = $this->valor($compra, $key);
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
        foreach (array_keys($activeColumns) as $key) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx++);
            if ($key === 'comprobante') {
                $sheet->setCellValue("{$col}{$totalRow}", 'TOTAL (' . $compras->count() . ' registros)');
            } elseif ($key === 'total') {
                $sheet->setCellValue("{$col}{$totalRow}", (float) $compras->sum('total'));
                $sheet->getStyle("{$col}{$totalRow}")->getNumberFormat()->setFormatCode('"S/ "#,##0.00');
                $sheet->getStyle("{$col}{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            } elseif ($key === 'saldo') {
                $sheet->setCellValue("{$col}{$totalRow}", $resumen['saldo']);
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
        $kpis = [
            'Total compras'   => $resumen['cantidad'],
            'Total comprado'  => 'S/ ' . number_format($resumen['total'], 2),
            'Total pagado'    => 'S/ ' . number_format($resumen['pagado'], 2),
            'Saldo pendiente' => 'S/ ' . number_format($resumen['saldo'], 2),
            'Por pagar'       => $resumen['pendiente'] . ' compras',
        ];
        foreach ($kpis as $label => $valor) {
            $sheet->setCellValue("A{$row}", $label . ':');
            $sheet->setCellValue("B{$row}", $valor);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->mergeCells("B{$row}:{$lastCol}{$row}");
            $row++;
        }

        // Bordes + autosize
        if ($compras->count() > 0) {
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
        Collection $compras,
        array $filtrosInfo,
        array $columnasVisibles,
        array $resumen,
        Empresa $empresa
    ): StreamedResponse {
        $activeColumns = $this->getActiveColumns($columnasVisibles);
        $nombre        = 'reporte-compras-' . now()->format('Ymd-His') . '.pdf';
        $usuarioNombre = auth()->user()?->name ?? 'Sistema';

        $rows = $compras->map(function (Compra $c) use ($activeColumns): array {
            $row = [];
            foreach (array_keys($activeColumns) as $key) {
                $row[$key] = $this->valor($c, $key);
            }
            return $row;
        });

        $totales = [
            'total' => (float) $compras->sum('total'),
            'saldo' => $resumen['saldo'],
        ];

        $moneyKeys = self::MONEY_COLS;

        $pdf = Pdf::loadView('reports.reporte-compras-pdf', compact(
            'compras', 'rows', 'filtrosInfo', 'activeColumns',
            'resumen', 'empresa', 'totales', 'moneyKeys', 'usuarioNombre'
        ))->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $nombre, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }
}

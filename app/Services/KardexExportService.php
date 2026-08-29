<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Kardex;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KardexExportService
{
    private function baseQuery(Empresa $empresa, array $filtros): Builder
    {
        $q = Kardex::where('empresa_id', $empresa->id)
            ->with(['user'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        if (! empty($filtros['producto'])) {
            [$tipo, $id] = explode(':', $filtros['producto'], 2);
            $tipo === 'v'
                ? $q->where('variante_id', (int) $id)
                : $q->where('producto_id', (int) $id)->whereNull('variante_id');
        }
        if (! empty($filtros['desde'])) {
            $q->whereDate('fecha', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $q->whereDate('fecha', '<=', $filtros['hasta']);
        }
        if (! empty($filtros['tipo'])) {
            $q->where('tipo', $filtros['tipo']);
        }
        if (! empty($filtros['origen'])) {
            $q->where('movible_type', $filtros['origen']);
        }

        return $q;
    }

    public function generarPdf(Empresa $empresa, array $filtros, array $resumen): StreamedResponse
    {
        $movimientos   = $this->baseQuery($empresa, $filtros)->get();
        $usuarioNombre = auth()->user()?->name ?? 'Sistema';
        $nombre        = 'kardex-' . now()->format('Ymd-His') . '.pdf';

        $origenMeta = [
            'App\\Models\\Ajuste' => 'Ajuste',
            'App\\Models\\Compra' => 'Compra',
            'App\\Models\\Venta'  => 'Venta',
        ];

        $pdf = Pdf::loadView('reports.kardex-pdf', compact(
            'movimientos', 'empresa', 'filtros', 'resumen', 'usuarioNombre', 'origenMeta'
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

    public function generarExcel(Empresa $empresa, array $filtros, array $resumen): StreamedResponse
    {
        $movimientos = $this->baseQuery($empresa, $filtros)->get();
        $nombre      = 'kardex-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($movimientos, $empresa, $filtros, $resumen) {
            (new Xlsx($this->buildSpreadsheet($movimientos, $empresa, $filtros, $resumen)))->save('php://output');
        }, $nombre, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    private function buildSpreadsheet(Collection $movimientos, Empresa $empresa, array $filtros, array $resumen): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kardex');

        $colorHeader  = '1E3A5F';
        $colorSubhead = '2C5282';
        $colorAlt     = 'EBF0F8';
        $colorTotal   = 'FFF3CD';
        $colorText    = 'FFFFFF';

        $cols    = ['Fecha', 'Producto', 'Concepto', 'Origen', 'Tipo', 'Cantidad', 'Stock Antes', 'Stock Después', 'Usuario'];
        $lastCol = Coordinate::stringFromColumnIndex(count($cols));
        $usuario = auth()->user()?->name ?? 'Sistema';
        $row     = 1;

        // Empresa
        $sheet->setCellValue("A{$row}", strtoupper($empresa->nombre));
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => $colorText]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorHeader]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        // RUC
        $sheet->setCellValue("A{$row}", 'RUC ' . ($empresa->ruc ?? ''));
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['size' => 10, 'color' => ['argb' => $colorText]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorHeader]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;

        // Título
        $sheet->setCellValue("A{$row}", 'KARDEX DE INVENTARIO');
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['argb' => $colorText]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorSubhead]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        // Generado por
        $sheet->setCellValue("A{$row}", 'Generado: ' . now()->format('d/m/Y H:i') . '  —  ' . $usuario);
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => $colorText]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorSubhead]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;
        $row++; // blank

        // Filtros
        $filtrosInfo = $this->buildFiltrosInfo($filtros);
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

        // Resumen
        $sheet->setCellValue("A{$row}", 'Total: ' . $resumen['total'] . '   Entradas: ' . $resumen['entradas'] . '   Salidas: ' . $resumen['salidas']);
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'EBF0F8']],
        ]);
        $row++;
        $row++;

        // Encabezados
        $headerRow = $row;
        foreach ($cols as $idx => $label) {
            $cl = Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->setCellValue("{$cl}{$row}", $label);
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => $colorText]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorHeader]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '164172']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(16);
        $row++;

        $origenLabels = [
            'App\\Models\\Ajuste' => 'Ajuste',
            'App\\Models\\Compra' => 'Compra',
            'App\\Models\\Venta'  => 'Venta',
        ];

        // Datos
        foreach ($movimientos as $i => $mov) {
            $bgFill    = $i % 2 === 0 ? $colorAlt : 'FFFFFF';
            $esEntrada = $mov->tipo === 'entrada';

            $rowData = [
                \Carbon\Carbon::parse($mov->fecha)->format('d/m/Y H:i'),
                $mov->producto_nombre . ($mov->variante_nombre ? ' (' . $mov->variante_nombre . ')' : ''),
                $mov->concepto,
                $origenLabels[$mov->movible_type] ?? '—',
                ucfirst($mov->tipo),
                ($esEntrada ? '+' : '-') . number_format((float) $mov->cantidad, 2),
                number_format((float) $mov->stock_antes, 2),
                number_format((float) $mov->stock_despues, 2),
                $mov->user?->name ?? 'Sistema',
            ];

            foreach ($rowData as $idx => $value) {
                $cl = Coordinate::stringFromColumnIndex($idx + 1);
                $sheet->setCellValue("{$cl}{$row}", $value);
            }

            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgFill]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'D1D9E6']]],
                'font'    => ['size' => 9],
            ]);

            $row++;
        }

        // Fila total
        $sheet->setCellValue("A{$row}", 'TOTAL (' . $movimientos->count() . ' movimientos)');
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 9],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorTotal]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'E2A800']]],
        ]);

        // Anchos
        foreach ([16, 30, 30, 10, 10, 12, 14, 14, 18] as $idx => $width) {
            $sheet->getColumnDimensionByColumn($idx + 1)->setWidth($width);
        }

        return $spreadsheet;
    }

    private function buildFiltrosInfo(array $filtros): array
    {
        $info = [];
        if (! empty($filtros['desde'])) {
            $info['Desde'] = \Carbon\Carbon::parse($filtros['desde'])->format('d/m/Y');
        }
        if (! empty($filtros['hasta'])) {
            $info['Hasta'] = \Carbon\Carbon::parse($filtros['hasta'])->format('d/m/Y');
        }
        if (! empty($filtros['tipo'])) {
            $info['Tipo'] = ucfirst($filtros['tipo']);
        }
        if (! empty($filtros['origen'])) {
            $labels = ['App\\Models\\Ajuste' => 'Ajuste', 'App\\Models\\Compra' => 'Compra', 'App\\Models\\Venta' => 'Venta'];
            $info['Origen'] = $labels[$filtros['origen']] ?? $filtros['origen'];
        }
        return $info;
    }
}

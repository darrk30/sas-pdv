<?php

namespace App\Services;

use App\Models\AjusteDetalle;
use App\Models\Empresa;
use App\Models\Inventario;
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

class InventarioExportService
{
    private function baseQuery(Empresa $empresa): Builder
    {
        return Inventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('estado_almacen', 'activo')
            ->whereHas('producto', fn(Builder $q) => $q->where('estado', '!=', 'archivado'))
            ->with(['producto', 'variante.valores.valor', 'variante.producto'])
            ->orderByRaw("FIELD(estado_inventario, 'agotado', 'por_agotarse', 'disponible')");
    }

    private function resolverNombre(Inventario $inv): string
    {
        if ($inv->variante_id && $inv->variante) {
            return AjusteDetalle::generarNombre(null, $inv->variante);
        }
        return $inv->producto?->nombre ?? '—';
    }

    private function resolverCodigo(Inventario $inv): string
    {
        if ($inv->variante_id && $inv->variante) {
            return $inv->variante->codigo ?? '—';
        }
        return $inv->producto?->codigo_interno ?? '—';
    }

    private function resolverEstado(Inventario $inv): string
    {
        return match ($inv->estado_inventario?->value ?? '') {
            'disponible'   => 'Disponible',
            'por_agotarse' => 'Por agotarse',
            'agotado'      => 'Agotado',
            default        => '—',
        };
    }

    public function generarPdf(Empresa $empresa, array $stats): StreamedResponse
    {
        $registros     = $this->baseQuery($empresa)->get();
        $usuarioNombre = auth()->user()?->name ?? 'Sistema';
        $nombre        = 'inventario-' . now()->format('Ymd-His') . '.pdf';

        $pdf = Pdf::loadView('reports.inventario-pdf', compact(
            'registros', 'empresa', 'stats', 'usuarioNombre'
        ))
        ->setPaper('a4', 'portrait')
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

    public function generarExcel(Empresa $empresa, array $stats): StreamedResponse
    {
        $registros = $this->baseQuery($empresa)->get();
        $nombre    = 'inventario-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($registros, $empresa, $stats) {
            (new Xlsx($this->buildSpreadsheet($registros, $empresa, $stats)))->save('php://output');
        }, $nombre, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    private function buildSpreadsheet(Collection $registros, Empresa $empresa, array $stats): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventario');

        $colorHeader  = '1E3A5F';
        $colorSubhead = '2C5282';
        $colorAlt     = 'EBF0F8';
        $colorTotal   = 'FFF3CD';
        $colorText    = 'FFFFFF';

        $cols     = ['Producto', 'Código', 'Stock Actual', 'Stock Mínimo', 'Estado'];
        $colCount = count($cols);
        $lastCol  = Coordinate::stringFromColumnIndex($colCount);
        $usuario  = auth()->user()?->name ?? 'Sistema';
        $row      = 1;

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
        $sheet->setCellValue("A{$row}", 'REPORTE DE INVENTARIO');
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

        // Resumen stats
        $sheet->setCellValue("A{$row}", 'RESUMEN DE STOCK');
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'DCE8F5']],
        ]);
        $row++;

        foreach ([
            'Total productos' => $stats['total'],
            'Disponibles'     => $stats['disponible'],
            'Por agotarse'    => $stats['por_agotarse'],
            'Agotados'        => $stats['agotado'],
        ] as $label => $value) {
            $sheet->setCellValue("A{$row}", $label . ':');
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->mergeCells("B{$row}:{$lastCol}{$row}");
            $row++;
        }
        $row++; // blank

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

        // Datos
        foreach ($registros as $i => $inv) {
            $bgFill = $i % 2 === 0 ? $colorAlt : 'FFFFFF';

            $rowData = [
                $this->resolverNombre($inv),
                $this->resolverCodigo($inv),
                (float) $inv->stock_real,
                (float) ($inv->stock_minimo ?? 0),
                $this->resolverEstado($inv),
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

            // Números alineados a la derecha
            foreach ([3, 4] as $numIdx) {
                $cl = Coordinate::stringFromColumnIndex($numIdx);
                $sheet->getStyle("{$cl}{$row}")->getNumberFormat()->setFormatCode('0.##');
                $sheet->getStyle("{$cl}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }

            $row++;
        }

        // Fila total
        $sheet->setCellValue("A{$row}", 'TOTAL (' . $registros->count() . ' registros)');
        $sheet->mergeCells("A{$row}:B{$row}");
        $totalStock = $registros->sum(fn($r) => (float) $r->stock_real);
        $sheet->setCellValue("C{$row}", $totalStock);
        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('0.##');
        $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 9],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colorTotal]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'E2A800']]],
        ]);

        // Ancho de columnas
        foreach ([45, 18, 14, 14, 16] as $idx => $width) {
            $sheet->getColumnDimensionByColumn($idx + 1)->setWidth($width);
        }

        return $spreadsheet;
    }
}

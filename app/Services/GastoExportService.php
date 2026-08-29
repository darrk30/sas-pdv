<?php

namespace App\Services;

use App\Enums\EstadoGasto;
use App\Models\Empresa;
use App\Models\Gasto;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GastoExportService
{
    private const COLOR_HDR = '1E3A5F';
    private const COLOR_BRD = 'CBD5E1';
    private const COLOR_ZEB = 'F0F4FA';

    // ── PDF ───────────────────────────────────────────────────────────────────

    public function exportarPdf(Empresa $empresa): StreamedResponse
    {
        $gastos  = $this->baseQuery($empresa)->get();
        $resumen = $this->calcularResumen($gastos);

        $pdf = Pdf::loadView('reports.gastos-pdf', [
            'empresa'       => $empresa,
            'gastos'        => $gastos,
            'resumen'       => $resumen,
            'usuarioNombre' => auth()->user()?->name ?? 'Sistema',
        ])->setPaper('a4', 'landscape')
        ->setOption([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => false,
            'defaultFont'          => 'DejaVu Sans',
        ]);

        $nombre = 'gastos-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $nombre,
            ['Content-Type' => 'application/pdf']
        );
    }

    // ── Excel ─────────────────────────────────────────────────────────────────

    public function exportarExcel(Empresa $empresa): StreamedResponse
    {
        $gastos      = $this->baseQuery($empresa)->get();
        $resumen     = $this->calcularResumen($gastos);
        $spreadsheet = $this->buildSpreadsheet($empresa, $gastos, $resumen);
        $nombre      = 'gastos-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $nombre, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    // ── Shared ────────────────────────────────────────────────────────────────

    private function baseQuery(Empresa $empresa)
    {
        return Gasto::where('empresa_id', $empresa->id)
            ->with(['registradoPor', 'empleado'])
            ->orderBy('fecha', 'desc')
            ->orderBy('correlativo', 'desc');
    }

    private function calcularResumen($gastos): array
    {
        $activos = $gastos->where('estado', '!=', EstadoGasto::Anulado);

        return [
            'total'      => $gastos->count(),
            'activos'    => $activos->count(),
            'suma'       => $activos->sum('monto'),
            'aprobados'  => $activos->where('estado', EstadoGasto::Aprobado)->sum('monto'),
            'pendientes' => $activos->where('estado', EstadoGasto::Pendiente)->sum('monto'),
            'anulados'   => $gastos->where('estado', EstadoGasto::Anulado)->count(),
        ];
    }

    private function buildSpreadsheet(Empresa $empresa, $gastos, array $resumen): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Gastos');
        $lastCol     = 'J';

        // Fila 1: empresa
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', strtoupper($empresa->nombre) . ' — REPORTE DE GASTOS — ' . now()->format('d/m/Y H:i'));
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::COLOR_HDR]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        // Fila 2: resumen
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2',
            "Total registros: {$resumen['total']}  |  "
            . "Activos: {$resumen['activos']}  |  "
            . "Suma activos: S/ " . number_format($resumen['suma'], 2) . "  |  "
            . "Aprobados: S/ " . number_format($resumen['aprobados'], 2) . "  |  "
            . "Pendientes: S/ " . number_format($resumen['pendientes'], 2) . "  |  "
            . "Anulados: {$resumen['anulados']}"
        );
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF' . self::COLOR_HDR]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(18);

        // Fila 3: headers
        $headers = ['CÓDIGO', 'FECHA', 'CATEGORÍA', 'DESCRIPCIÓN', 'EMPLEADO', 'MONTO (S/)', 'ESTADO', 'REGISTRADO POR', 'ARCHIVO', 'REGISTRADO'];
        foreach ($headers as $i => $h) {
            $col = chr(ord('A') + $i);
            $sheet->setCellValue("{$col}3", $h);
            $sheet->getStyle("{$col}3")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 8, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::COLOR_HDR]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BRD]]],
            ]);
        }
        $sheet->getRowDimension(3)->setRowHeight(18);

        // Anchos
        foreach (['A'=>14,'B'=>12,'C'=>16,'D'=>40,'E'=>22,'F'=>12,'G'=>12,'H'=>22,'I'=>20,'J'=>16] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // Datos
        foreach ($gastos as $i => $g) {
            $row   = $i + 4;
            $zebra = ($i % 2 === 1) ? 'FF' . self::COLOR_ZEB : 'FFFFFFFF';

            $sheet->setCellValue("A{$row}", $g->serie . '-' . str_pad($g->correlativo, 6, '0', STR_PAD_LEFT));
            $sheet->setCellValue("B{$row}", $g->fecha?->format('d/m/Y') ?? '');
            $sheet->setCellValue("C{$row}", $g->categoria?->getLabel() ?? '');
            $sheet->setCellValue("D{$row}", $g->descripcion);
            $sheet->setCellValue("E{$row}", $g->empleado?->name ?? '—');
            $sheet->setCellValue("F{$row}", (float) $g->monto);
            $sheet->setCellValue("G{$row}", $g->estado?->getLabel() ?? '');
            $sheet->setCellValue("H{$row}", $g->registradoPor?->name ?? '');
            $sheet->setCellValue("I{$row}", $g->archivo_adjunto ? 'Sí' : 'No');
            $sheet->setCellValue("J{$row}", $g->created_at?->format('d/m/Y H:i') ?? '');

            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font'    => ['size' => 8],
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $zebra]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BRD]]],
            ]);
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        // Fila total
        $totalRow = $gastos->count() + 4;
        $sheet->setCellValue("E{$totalRow}", 'TOTAL');
        $sheet->setCellValue("F{$totalRow}", $gastos->where('estado', '!=', EstadoGasto::Anulado)->sum('monto'));
        $sheet->mergeCells("A{$totalRow}:D{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'TOTAL ({$gastos->count()} registros)');
        $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 8, 'color' => ['argb' => 'FF' . self::COLOR_HDR]],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . self::COLOR_HDR]]],
        ]);
        $sheet->getStyle("F{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');

        $sheet->freezePane('A4');

        return $spreadsheet;
    }
}

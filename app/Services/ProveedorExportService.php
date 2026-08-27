<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Proveedor;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProveedorExportService
{
    private const COLOR_HDR = '1E3A5F';
    private const COLOR_BRD = 'CBD5E1';
    private const COLOR_ZEB = 'F0F4FA';

    private const COLS = [
        'A' => ['TIPO_DOCUMENTO', 16],
        'B' => ['NUMERO_DOCUMENTO', 20],
        'C' => ['NOMBRE', 32],
        'D' => ['CORREO', 32],
        'E' => ['TELEFONO', 16],
        'F' => ['DIRECCION', 40],
        'G' => ['DEPARTAMENTO', 20],
        'H' => ['ESTADO', 14],
    ];

    public function exportar(Empresa $empresa): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet($empresa);
        $nombre      = 'proveedores-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $nombre, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    private function buildSpreadsheet(Empresa $empresa): Spreadsheet
    {
        $proveedores = Proveedor::where('empresa_id', $empresa->id)
            ->orderBy('nombre')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Proveedores');
        $lastCol = array_key_last(self::COLS);

        // ── Fila 1: empresa ────────────────────────────────────────────────────
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', strtoupper($empresa->nombre) . ' — RUC ' . ($empresa->ruc ?? '') . ' — Exportado: ' . now()->format('d/m/Y H:i'));
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF' . self::COLOR_HDR]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // ── Fila 2: headers ────────────────────────────────────────────────────
        foreach (self::COLS as $col => [$label, $width]) {
            $sheet->setCellValue("{$col}2", $label);
            $sheet->getStyle("{$col}2")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::COLOR_HDR]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BRD]]],
            ]);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension(2)->setRowHeight(20);

        // ── Datos ──────────────────────────────────────────────────────────────
        foreach ($proveedores as $i => $prov) {
            $row   = $i + 3;
            $zebra = ($i % 2 === 1) ? 'FF' . self::COLOR_ZEB : 'FFFFFFFF';

            $sheet->setCellValue("A{$row}", $prov->tipo_documento?->value ?? '');
            $sheet->setCellValue("B{$row}", $prov->numero_documento ?? '');
            $sheet->setCellValue("C{$row}", $prov->nombre ?? '');
            $sheet->setCellValue("D{$row}", $prov->correo ?? '');
            $sheet->setCellValue("E{$row}", $prov->telefono ?? '');
            $sheet->setCellValue("F{$row}", $prov->direccion ?? '');
            $sheet->setCellValue("G{$row}", $prov->departamento ?? '');
            $sheet->setCellValue("H{$row}", $prov->estado?->value ?? '');

            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font'    => ['size' => 9],
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $zebra]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BRD]]],
            ]);
        }

        // ── Fila total ─────────────────────────────────────────────────────────
        $totalRow = $proveedores->count() + 3;
        $sheet->mergeCells("A{$totalRow}:{$lastCol}{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'Total: ' . $proveedores->count() . ' proveedores exportados');
        $sheet->getStyle("A{$totalRow}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF' . self::COLOR_HDR]],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . self::COLOR_HDR]]],
        ]);

        $sheet->freezePane('A3');

        return $spreadsheet;
    }
}

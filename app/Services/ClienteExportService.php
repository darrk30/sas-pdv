<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Empresa;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClienteExportService
{
    private const COLOR_HDR = '1E3A5F';
    private const COLOR_BRD = 'CBD5E1';
    private const COLOR_ZEB = 'F0F4FA';

    private const COLS = [
        'A' => ['TIPO_DOCUMENTO', 16],
        'B' => ['NUMERO_DOCUMENTO', 20],
        'C' => ['NOMBRE', 28],
        'D' => ['APELLIDOS', 24],
        'E' => ['CORREO', 32],
        'F' => ['TELEFONO', 16],
        'G' => ['DIRECCION', 40],
    ];

    public function exportar(Empresa $empresa): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet($empresa);
        $nombre      = 'clientes-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $nombre, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    private function buildSpreadsheet(Empresa $empresa): Spreadsheet
    {
        $clientes = Cliente::where('empresa_id', $empresa->id)
            ->orderBy('nombre')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Clientes');
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
        foreach ($clientes as $i => $cliente) {
            $row    = $i + 3;
            $zebra  = ($i % 2 === 1) ? 'FF' . self::COLOR_ZEB : 'FFFFFFFF';

            $sheet->setCellValue("A{$row}", $cliente->tipo_documento?->value ?? '');
            $sheet->setCellValue("B{$row}", $cliente->numero_documento ?? '');
            $sheet->setCellValue("C{$row}", $cliente->nombre ?? '');
            $sheet->setCellValue("D{$row}", $cliente->apellidos ?? '');
            $sheet->setCellValue("E{$row}", $cliente->correo ?? '');
            $sheet->setCellValue("F{$row}", $cliente->telefono ?? '');
            $sheet->setCellValue("G{$row}", $cliente->direccion ?? '');

            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font'    => ['size' => 9],
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $zebra]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BRD]]],
            ]);
        }

        // ── Fila total ─────────────────────────────────────────────────────────
        $totalRow = $clientes->count() + 3;
        $sheet->mergeCells("A{$totalRow}:{$lastCol}{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'Total: ' . $clientes->count() . ' clientes exportados');
        $sheet->getStyle("A{$totalRow}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF' . self::COLOR_HDR]],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . self::COLOR_HDR]]],
        ]);

        $sheet->freezePane('A3');

        return $spreadsheet;
    }
}

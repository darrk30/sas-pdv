<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AjusteExcelTemplateService
{
    private const COLOR_REQ  = '1E3A5F';
    private const COLOR_OPT  = '2D6A9F';
    private const COLOR_TEXT = 'FFFFFF';
    private const COLOR_EJE  = 'FFF9E6';
    private const COLOR_BRD  = 'CBD5E1';

    private const COLS = [
        'A' => ['CODIGO_INTERNO (*)', 22, true,  'Requerido. Código interno del producto o de la variante. No distingue mayúsculas.'],
        'B' => ['UNIDAD',             14, false, 'Opcional. Nombre o símbolo de la unidad (Unidad, NIU, Docena, KG…). Si se deja en blanco se usa la unidad registrada del producto. Debe pertenecer a la misma dimensión que la unidad del producto.'],
        'C' => ['CANTIDAD (*)',        13, true,  'Requerido. Cantidad a ajustar. Usar punto (.) como separador decimal.'],
        'D' => ['COSTO_UNITARIO',     18, false, 'Opcional. Costo unitario con IGV incluido. Si se deja en blanco se toma el costo registrado del producto o variante.'],
    ];

    private const EJEMPLOS = [
        ['POLO-M-ROJO',  'NIU',    '10',  '25.00'],
        ['CAM-001',       '',      '5',   ''],
        ['BOTELLA-500ML', 'unidad', '20',  '1.50'],
    ];

    public function descargar(): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet();
        $nombre      = 'plantilla-ajuste-stock.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $nombre, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    private function buildSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ajuste Stock');

        $maxRows = 1002;

        // ── Fila 1: cabeceras ─────────────────────────────────────────────────
        foreach (self::COLS as $col => [$label, $width, $required]) {
            $sheet->setCellValue("{$col}1", $label);
            $color = $required ? self::COLOR_REQ : self::COLOR_OPT;
            $sheet->getStyle("{$col}1")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF' . self::COLOR_TEXT]],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $color]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BRD]]],
            ]);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension(1)->setRowHeight(20);

        // ── Filas de ejemplos (2-4) ───────────────────────────────────────────
        foreach (self::EJEMPLOS as $i => $ejemplo) {
            $r = 2 + $i;
            foreach (array_keys(self::COLS) as $j => $col) {
                $sheet->setCellValue("{$col}{$r}", $ejemplo[$j] ?? '');
                $sheet->getStyle("{$col}{$r}")->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF9E6']],
                    'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF665500']],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BRD]]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);
            }
            $sheet->getStyle("C{$r}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
            $sheet->getStyle("D{$r}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
            $sheet->getRowDimension($r)->setRowHeight(16);
        }

        // ── Filas de datos (5–1002) ───────────────────────────────────────────
        $dataStart = 2 + count(self::EJEMPLOS); // 5
        $sheet->getStyle("A{$dataStart}:D{$maxRows}")->applyFromArray([
            'font'    => ['size' => 9],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BRD]]],
        ]);
        $sheet->getStyle("C{$dataStart}:C{$maxRows}")->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
        $sheet->getStyle("D{$dataStart}:D{$maxRows}")->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);

        $sheet->freezePane("A{$dataStart}");

        return $spreadsheet;
    }
}

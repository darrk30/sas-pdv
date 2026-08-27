<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProveedorExcelTemplateService
{
    private const COLOR_REQ  = '1E3A5F';
    private const COLOR_OPT  = '2D6A9F';
    private const COLOR_TEXT = 'FFFFFF';
    private const COLOR_EJE  = 'FFF9E6';
    private const COLOR_VAL  = 'F0F7FF';
    private const COLOR_BRD  = 'CBD5E1';

    private const COLS = [
        'A' => ['TIPO_DOCUMENTO (*)', 16, true,  'Requerido. Selecciona: dni o ruc.'],
        'B' => ['NUMERO_DOCUMENTO (*)', 20, true, 'Requerido. 8 dígitos para DNI, 11 para RUC. Identifica si crear o actualizar.'],
        'C' => ['NOMBRE (*)',          28, true,  'Requerido. Nombre o razón social del proveedor.'],
        'D' => ['CORREO',              28, false, 'Opcional. Correo electrónico de contacto.'],
        'E' => ['TELEFONO',            16, false, 'Opcional. Teléfono de contacto.'],
        'F' => ['DIRECCION',           36, false, 'Opcional. Dirección del proveedor.'],
        'G' => ['DEPARTAMENTO',        20, false, 'Opcional. Departamento o región.'],
    ];

    private const EJEMPLO = ['ruc', '20123456789', 'Distribuidora Ejemplo S.A.C.', 'contacto@ejemplo.com', '014441234', 'Av. Industrial 456', 'Lima'];

    public function descargar(): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet();
        $nombre      = 'plantilla-proveedores.xlsx';

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
        $sheet->setTitle('Proveedores');

        $lastCol = array_key_last(self::COLS);
        $maxRows = 1002;

        // ── Fila 1: instrucciones ──────────────────────────────────────────────
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'IMPORTAR PROVEEDORES — Si el NUMERO_DOCUMENTO ya existe en la empresa se actualizará; si no existe se creará. (*) = requerido.');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF' . self::COLOR_REQ]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // ── Fila 2: headers ────────────────────────────────────────────────────
        foreach (self::COLS as $col => [$label, $width, $required, $hint]) {
            $sheet->setCellValue("{$col}2", $label);
            $color = $required ? self::COLOR_REQ : self::COLOR_OPT;
            $sheet->getStyle("{$col}2")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF' . self::COLOR_TEXT]],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $color]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BRD]]],
            ]);
            $sheet->getColumnDimension($col)->setWidth($width);

            $comment = $sheet->getComment("{$col}2");
            $comment->getText()->createTextRun($hint);
            $comment->setVisible(false);
        }
        $sheet->getRowDimension(2)->setRowHeight(22);

        // ── Fila 3: ejemplo ────────────────────────────────────────────────────
        foreach (self::COLS as $i => $col) {
            $idx = ord($i) - ord('A');
            $sheet->setCellValue("{$i}3", self::EJEMPLO[$idx] ?? '');
            $sheet->getStyle("{$i}3")->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::COLOR_EJE]],
                'font'    => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF888800']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BRD]]],
            ]);
        }

        // ── Filas 4–1002: datos ────────────────────────────────────────────────
        $sheet->getStyle("A4:{$lastCol}{$maxRows}")->applyFromArray([
            'font'    => ['size' => 9],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BRD]]],
        ]);

        // Dropdown TIPO_DOCUMENTO
        for ($r = 3; $r <= $maxRows; $r++) {
            $v = $sheet->getCell("A{$r}")->getDataValidation();
            $v->setType(DataValidation::TYPE_LIST);
            $v->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $v->setAllowBlank(false);
            $v->setShowInputMessage(true);
            $v->setShowErrorMessage(true);
            $v->setShowDropDown(true);
            $v->setFormula1('"dni,ruc"');
            $v->setErrorTitle('Valor inválido');
            $v->setError('Elige: dni o ruc');

            if ($r >= 4) {
                $sheet->getStyle("A{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::COLOR_VAL]],
                ]);
            }
        }

        $sheet->freezePane('A4');

        return $spreadsheet;
    }
}

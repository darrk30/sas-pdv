<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClienteExcelTemplateService
{
    private const COLOR_REQ  = '1E3A5F';
    private const COLOR_OPT  = '2D6A9F';
    private const COLOR_TEXT = 'FFFFFF';
    private const COLOR_EJE  = 'FFF9E6';
    private const COLOR_VAL  = 'F0F7FF';
    private const COLOR_BRD  = 'CBD5E1';

    private const COLS = [
        'A' => ['TIPO_DOCUMENTO (*)', 16, true,  'dni,ruc', 'Requerido. Selecciona: dni o ruc.'],
        'B' => ['NUMERO_DOCUMENTO (*)', 20, true,  null,    'Requerido. 8 dígitos para DNI, 11 para RUC. Identifica si crear o actualizar.'],
        'C' => ['NOMBRE (*)',          24, true,  null,     'Requerido. Nombre o razón social.'],
        'D' => ['APELLIDOS',           20, false, null,     'Opcional. Apellidos del cliente.'],
        'E' => ['CORREO',              28, false, null,     'Opcional. Correo electrónico.'],
        'F' => ['TELEFONO',            16, false, null,     'Opcional. Teléfono de contacto.'],
        'G' => ['DIRECCION',           36, false, null,     'Opcional. Dirección del cliente.'],
    ];

    private const EJEMPLO = ['dni', '12345678', 'Juan', 'Pérez García', 'juan@email.com', '987654321', 'Av. Lima 123'];

    public function descargar(): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet();
        $nombre      = 'plantilla-clientes.xlsx';

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
        $sheet->setTitle('Clientes');

        $lastCol   = array_key_last(self::COLS);
        $maxRows   = 1002;

        // ── Fila 1: instrucciones ──────────────────────────────────────────────
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'IMPORTAR CLIENTES — Si el NUMERO_DOCUMENTO ya existe en la empresa se actualizará; si no existe se creará. (*) = requerido.');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF' . self::COLOR_REQ]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // ── Fila 2: headers ────────────────────────────────────────────────────
        foreach (self::COLS as $col => [$label, $width, $required]) {
            $sheet->setCellValue("{$col}2", $label);
            $color = $required ? self::COLOR_REQ : self::COLOR_OPT;
            $sheet->getStyle("{$col}2")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF' . self::COLOR_TEXT]],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $color]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BRD]]],
            ]);
            $sheet->getColumnDimension($col)->setWidth($width);

            // Comentario con instrucciones
            $comment = $sheet->getComment("{$col}2");
            $comment->getText()->createTextRun(self::COLS[$col][4]);
            $comment->setVisible(false);
        }
        $sheet->getRowDimension(2)->setRowHeight(22);

        // ── Fila 3: ejemplo ────────────────────────────────────────────────────
        foreach (self::COLS as $i => $col) {
            $letter = $i;
            $idx    = ord($i) - ord('A');
            $sheet->setCellValue("{$letter}3", self::EJEMPLO[$idx] ?? '');
            $sheet->getStyle("{$letter}3")->applyFromArray([
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

        // Validación dropdown para TIPO_DOCUMENTO (col A)
        $validation = $sheet->getCell('A4')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $validation->setAllowBlank(false);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"dni,ruc"');
        $validation->setErrorTitle('Valor inválido');
        $validation->setError('Elige: dni o ruc');

        for ($r = 5; $r <= $maxRows; $r++) {
            $v = clone $validation;
            $sheet->getCell("A{$r}")->setDataValidation($v);
            $sheet->getStyle("A{$r}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::COLOR_VAL]],
            ]);
        }
        $sheet->getStyle('A4')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::COLOR_VAL]],
        ]);

        // Freeze header rows
        $sheet->freezePane('A4');

        return $spreadsheet;
    }
}

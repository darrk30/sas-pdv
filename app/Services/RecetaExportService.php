<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Receta;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecetaExportService
{
    private const HDR_BG  = '1D4ED8';
    private const HDR_FG  = 'FFFFFF';
    private const BG_ODD  = 'EFF6FF';
    private const BG_EVEN = 'DBEAFE';
    private const BG_GRP  = 'F0FDF4';  // primera fila del grupo
    private const BORDER  = 'CBD5E1';

    // Columnas: A=codigo_producto B=nombre_receta C=nombre_producto
    //           D=codigo_insumo  E=nombre_insumo  F=cantidad  G=unidad
    private const COLS = [
        'A' => ['codigo_producto', 18],
        'B' => ['nombre_receta',   28],
        'C' => ['nombre_producto', 28],
        'D' => ['codigo_insumo',   18],
        'E' => ['nombre_insumo',   28],
        'F' => ['cantidad',        12],
        'G' => ['unidad',          10],
    ];

    public function generarPlantilla(): StreamedResponse
    {
        $sp = new Spreadsheet();
        $ws = $sp->getActiveSheet()->setTitle('Recetas');

        // 5 columnas: sin nombres (solo códigos)
        $cols5 = ['A' => ['codigo_producto', 20], 'B' => ['nombre_receta', 28], 'C' => ['codigo_insumo', 20], 'D' => ['cantidad', 12], 'E' => ['unidad', 10]];
        foreach ($cols5 as $col => [$label]) {
            $ws->setCellValue("{$col}1", $label);
        }
        $ws->getStyle('A1:E1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => self::HDR_FG], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => self::HDR_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $ws->getRowDimension(1)->setRowHeight(22);

        $ejemplos = [
            ['PERF001', 'Perfume Rico 50ml',  'ALC001',  '0.04', 'L'  ],
            ['',        '',                    'ESENC01', '0.005','L'  ],
            ['',        '',                    'FRASCO1', '1',    'UND'],
            ['CAFE001', 'Café Especial 250g', 'CAFE_GR', '20',   'gr' ],
            ['',        '',                    'VASO001', '1',    ''   ],
        ];

        foreach ($ejemplos as $i => $fila) {
            $row = $i + 2;
            $ws->fromArray($fila, null, "A{$row}");
            $bg = $i < 3 ? self::BG_ODD : self::BG_EVEN;
            $ws->getStyle("A{$row}:E{$row}")->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => ($fila[0] !== '' ? self::BG_GRP : $bg)]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => self::BORDER]]],
                'font'    => $fila[0] !== '' ? ['bold' => true] : [],
            ]);
            $ws->getRowDimension($row)->setRowHeight(17);
        }

        foreach ($cols5 as $col => [, $width]) {
            $ws->getColumnDimension($col)->setWidth($width);
        }
        $ws->getStyle('D2:D10')->getNumberFormat()->setFormatCode('0.####');
        $ws->freezePane('A2');

        return response()->streamDownload(
            fn () => (new Xlsx($sp))->save('php://output'),
            'plantilla-recetas.xlsx',
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="plantilla-recetas.xlsx"',
            ]
        );
    }

    public function exportar(Empresa $empresa): StreamedResponse
    {
        $recetas = Receta::where('empresa_id', $empresa->id)
            ->with([
                'producto',
                'variante.producto',
                'detalles.insumoProducto',
                'detalles.insumoVariante.producto',
                'detalles.unidadMedida',
            ])
            ->orderBy('nombre')
            ->get();

        $sp = new Spreadsheet();
        $ws = $sp->getActiveSheet()->setTitle('Recetas');

        // ── Cabecera ──────────────────────────────────────────────────────────
        foreach (self::COLS as $col => [$label]) {
            $ws->setCellValue("{$col}1", $label);
        }
        $ws->getStyle('A1:G1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => self::HDR_FG], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => self::HDR_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $ws->getRowDimension(1)->setRowHeight(22);

        // Nota en H1: columnas C y E son solo referencia
        $ws->setCellValue('H1', '(C y E son informativos — no se usan al importar)');
        $ws->getStyle('H1')->applyFromArray([
            'font'  => ['italic' => true, 'color' => ['rgb' => '94A3B8'], 'size' => 10],
            'fill'  => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => self::HDR_BG]],
        ]);
        $ws->getColumnDimension('H')->setWidth(44);

        // ── Filas ─────────────────────────────────────────────────────────────
        $row    = 2;
        $grpIdx = 0;

        foreach ($recetas as $receta) {
            $detalles = $receta->detalles;
            if ($detalles->isEmpty()) continue;

            $codProducto    = $receta->variante_id
                ? ($receta->variante?->codigo ?? '')
                : ($receta->producto?->codigo_interno ?? '');

            $nombreProducto = $receta->variante_id
                ? (($receta->variante?->producto?->nombre ?? '') . ' — ' .
                   ($receta->variante?->valores
                       ->map(fn ($p) => $p->valor?->nombre)->filter()->implode(' / ') ?? ''))
                : ($receta->producto?->nombre ?? '');

            $bg       = ($grpIdx % 2 === 0) ? self::BG_ODD : self::BG_EVEN;
            $firstRow = true;

            foreach ($detalles as $det) {
                $codInsumo    = $det->insumo_variante_id
                    ? ($det->insumoVariante?->codigo ?? '')
                    : ($det->insumoProducto?->codigo_interno ?? '');

                $nombreInsumo = $det->insumo_variante_id
                    ? (($det->insumoVariante?->producto?->nombre ?? '') . ' — ' .
                       ($det->insumoVariante?->valores
                           ->map(fn ($p) => $p->valor?->nombre)->filter()->implode(' / ') ?? ''))
                    : ($det->insumoProducto?->nombre ?? '');

                $ws->setCellValue("D{$row}", $codInsumo);
                $ws->setCellValue("E{$row}", $nombreInsumo);
                $ws->setCellValue("F{$row}", $det->cantidad);
                $ws->setCellValue("G{$row}", $det->unidadMedida?->simbolo ?? '');

                if ($firstRow) {
                    $ws->setCellValue("A{$row}", $codProducto);
                    $ws->setCellValue("B{$row}", $receta->nombre);
                    $ws->setCellValue("C{$row}", $nombreProducto);

                    $ws->getStyle("A{$row}:G{$row}")->applyFromArray([
                        'fill'    => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => self::BG_GRP]],
                        'borders' => [
                            'top'        => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '93C5FD']],
                            'allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => self::BORDER]],
                        ],
                        'font' => ['bold' => true],
                    ]);
                    $firstRow = false;
                } else {
                    $ws->getStyle("A{$row}:G{$row}")->applyFromArray([
                        'fill'    => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $bg]],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => self::BORDER]]],
                    ]);
                }

                $ws->getRowDimension($row)->setRowHeight(17);
                $row++;
            }

            $grpIdx++;
        }

        // ── Anchos y formato ──────────────────────────────────────────────────
        foreach (self::COLS as $col => [, $width]) {
            $ws->getColumnDimension($col)->setWidth($width);
        }
        $ws->getStyle("F2:F{$row}")->getNumberFormat()->setFormatCode('0.####');
        $ws->freezePane('A2');

        $nombre = 'recetas-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(
            fn () => (new Xlsx($sp))->save('php://output'),
            $nombre,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
            ]
        );
    }
}

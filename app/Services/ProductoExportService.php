<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Producto;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductoExportService
{
    private const COLOR_INFO  = '1E3A5F';
    private const COLOR_VENTA = '1A5736';
    private const COLOR_VAR   = '4A2080';
    private const COLOR_CFG   = '1A4D5F';
    private const COLOR_BRD   = 'CBD5E1';
    private const COLOR_ZEB   = 'F0F4FA';

    private const COLS = [
        // ── Información ──────────────────────────────────────────────
        'A' => ['NOMBRE',            40, 'info'],
        'B' => ['PRECIO_VENTA',      16, 'info'],
        'C' => ['PRECIO_COSTO',      16, 'info'],
        'D' => ['% DESCUENTO',       14, 'info'],
        'E' => ['PRECIO_FINAL',      20, 'info'],
        'F' => ['UNIDAD_MEDIDA',     18, 'info'],
        'G' => ['ESTADO',            14, 'info'],
        'H' => ['STOCK_MINIMO',      16, 'info'],
        'I' => ['STOCK_TOTAL',       14, 'info'],
        'J' => ['CODIGO_INTERNO',    18, 'info'],
        'K' => ['CODIGO_BARRAS',     18, 'info'],
        'L' => ['DESCRIPCION',       44, 'info'],
        // ── Venta ────────────────────────────────────────────────────
        'M' => ['CATEGORIA',         22, 'venta'],
        'N' => ['MARCA',             22, 'venta'],
        'O' => ['AREA_PRODUCCION',   22, 'venta'],
        'P' => ['ETIQUETA',          18, 'venta'],
        // ── Variantes ────────────────────────────────────────────────
        'Q' => ['VARIANTES',         38, 'var'],
        // ── Configuración ────────────────────────────────────────────
        'R' => ['CORTESIA',          14, 'cfg'],
        'S' => ['VISIBLE_EN_CARTA',  18, 'cfg'],
        'T' => ['CONTROL_STOCK',     16, 'cfg'],
        'U' => ['VENTA_SIN_STOCK',   18, 'cfg'],
        'V' => ['VENDIBLE',          14, 'cfg'],
        'W' => ['ORDEN',             10, 'cfg'],
    ];

    private const GROUPS = [
        'info'  => ['INFORMACIÓN',  'A', 'L', self::COLOR_INFO],
        'venta' => ['VENTA',        'M', 'P', self::COLOR_VENTA],
        'var'   => ['VARIANTES',    'Q', 'Q', self::COLOR_VAR],
        'cfg'   => ['CONFIGURACIÓN','R', 'W', self::COLOR_CFG],
    ];

    public function exportar(Empresa $empresa): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet($empresa);
        $nombre      = 'productos-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $nombre, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    private function buildSpreadsheet(Empresa $empresa): Spreadsheet
    {
        $productos = Producto::where('empresa_id', $empresa->id)
            ->with([
                'unidadMedida',
                'categoria',
                'marca',
                'produccion',
                'inventario',
                'variantes' => fn($q) => $q->where('estado', 'activo')
                    ->with(['valores.valor', 'inventario']),
            ])
            ->orderBy('nombre')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos');

        $lastCol = array_key_last(self::COLS);

        // ── Fila 1: empresa ────────────────────────────────────────────────────
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1',
            strtoupper($empresa->nombre)
            . ' — Exportado: ' . now()->format('d/m/Y H:i')
        );
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF' . self::COLOR_INFO]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // ── Fila 2: grupos ─────────────────────────────────────────────────────
        foreach (self::GROUPS as [$label, $from, $to, $color]) {
            $range = ($from === $to) ? "{$from}2" : "{$from}2:{$to}2";
            if ($from !== $to) {
                $sheet->mergeCells("{$from}2:{$to}2");
            }
            $sheet->setCellValue("{$from}2", $label);
            $sheet->getStyle($range)->applyFromArray([
                'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $color]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
            ]);
        }
        $sheet->getRowDimension(2)->setRowHeight(18);

        // ── Fila 3: columnas ───────────────────────────────────────────────────
        foreach (self::COLS as $col => [$label, $width, $group]) {
            [, , , $color] = self::GROUPS[$group];
            $sheet->setCellValue("{$col}3", $label);
            $sheet->getStyle("{$col}3")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'CC' . $color]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BRD]]],
            ]);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension(3)->setRowHeight(20);

        // ── Datos ──────────────────────────────────────────────────────────────
        foreach ($productos as $i => $p) {
            $row   = $i + 4;
            $zebra = ($i % 2 === 1) ? 'FF' . self::COLOR_ZEB : 'FFFFFFFF';

            // Stock calculado desde relaciones pre-cargadas
            $variantes  = $p->variantes ?? collect();
            $tieneVar   = $variantes->isNotEmpty();
            $stockTotal = $tieneVar
                ? $variantes->sum(fn($v) => (float) ($v->inventario?->stock_real ?? 0))
                : (float) ($p->inventario?->stock_real ?? 0);

            // Variantes: "S - Rojo (2.00)" una por línea en la misma celda
            $variantesText = $tieneVar
                ? $variantes->map(function ($v) {
                    $nombre = $v->valores
                        ->map(fn($pav) => $pav->valor?->nombre ?? '')
                        ->filter()
                        ->implode(' - ');
                    $nombre = $nombre ?: '—';
                    $stock  = number_format((float) ($v->inventario?->stock_real ?? 0), 2);
                    return "{$nombre} ({$stock})";
                })->implode("\n")
                : '';

            // ── Información ─────────────────────────────────────────
            $sheet->setCellValue("A{$row}", $p->nombre ?? '');
            $sheet->setCellValue("B{$row}", (float) ($p->precio_venta ?? 0));
            $sheet->setCellValue("C{$row}", (float) ($p->precio_costo ?? 0));
            $sheet->setCellValue("D{$row}", (float) ($p->porcentaje_descuento ?? 0));
            $sheet->setCellValue("E{$row}", (float) ($p->precio_con_descuento ?? 0));
            $sheet->setCellValue("F{$row}", $p->unidadMedida?->nombre ?? '');
            $sheet->setCellValue("G{$row}", $p->estado?->getLabel() ?? $p->estado?->value ?? '');
            $sheet->setCellValue("H{$row}", (float) ($p->inventario?->stock_minimo ?? 0));
            $sheet->setCellValue("I{$row}", $stockTotal);
            $sheet->setCellValueExplicit("J{$row}", $p->codigo_interno ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("K{$row}", $p->codigo_barras ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("L{$row}", strip_tags($p->descripcion ?? ''));

            // ── Venta ───────────────────────────────────────────────
            $sheet->setCellValue("M{$row}", $p->categoria?->nombre ?? '');
            $sheet->setCellValue("N{$row}", $p->marca?->nombre ?? '');
            $sheet->setCellValue("O{$row}", $p->produccion?->nombre ?? '');
            $sheet->setCellValue("P{$row}", $p->etiqueta?->getLabel() ?? '');

            // ── Variantes ───────────────────────────────────────────
            $sheet->setCellValue("Q{$row}", $variantesText);
            if ($variantesText) {
                $sheet->getStyle("Q{$row}")->getAlignment()->setWrapText(true);
            }

            // ── Configuración ───────────────────────────────────────
            $sheet->setCellValue("R{$row}", $p->es_cortesia        ? 'Sí' : 'No');
            $sheet->setCellValue("S{$row}", $p->visible_en_carta   ? 'Sí' : 'No');
            $sheet->setCellValue("T{$row}", $p->control_de_stock   ? 'Sí' : 'No');
            $sheet->setCellValue("U{$row}", $p->venta_sin_stock    ? 'Sí' : 'No');
            $sheet->setCellValue("V{$row}", $p->vendible           ? 'Sí' : 'No');
            $sheet->setCellValue("W{$row}", (int) ($p->orden ?? 0));

            // Estilo de fila
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font'      => ['size' => 9],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $zebra]],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BRD]]],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
            ]);

            // Números → derecha
            foreach (['B', 'C', 'D', 'E', 'H', 'I', 'W'] as $nc) {
                $sheet->getStyle("{$nc}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            // Booleanos / estado → centro
            foreach (['G', 'R', 'S', 'T', 'U', 'V'] as $cc) {
                $sheet->getStyle("{$cc}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        // ── Fila total ─────────────────────────────────────────────────────────
        $totalRow = $productos->count() + 4;
        $sheet->mergeCells("A{$totalRow}:{$lastCol}{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'Total: ' . $productos->count() . ' productos exportados');
        $sheet->getStyle("A{$totalRow}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF' . self::COLOR_INFO]],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . self::COLOR_INFO]]],
        ]);

        $sheet->freezePane('A4');

        return $spreadsheet;
    }
}

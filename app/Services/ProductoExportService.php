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

    // ── Hoja 1: Productos ─────────────────────────────────────────────────────
    private const COLS = [
        'A' => ['NOMBRE',           40, 'info'],
        'B' => ['PRECIO_VENTA',     16, 'info'],
        'C' => ['PRECIO_COSTO',     16, 'info'],
        'D' => ['% DESCUENTO',      14, 'info'],
        'E' => ['PRECIO_FINAL',     20, 'info'],
        'F' => ['UNIDAD_MEDIDA',    18, 'info'],
        'G' => ['ESTADO',           14, 'info'],
        'H' => ['STOCK_MINIMO',     16, 'info'],
        'I' => ['STOCK_TOTAL',      14, 'info'],
        'J' => ['CODIGO_INTERNO',   18, 'info'],
        'K' => ['CODIGO_BARRAS',    18, 'info'],
        'L' => ['DESCRIPCION',      44, 'info'],
        'M' => ['CATEGORIA',        22, 'venta'],
        'N' => ['MARCA',            22, 'venta'],
        'O' => ['AREA_PRODUCCION',  22, 'venta'],
        'P' => ['ETIQUETA',         18, 'venta'],
        'Q' => ['# VARIANTES',      16, 'var'],
        'R' => ['CORTESIA',         14, 'cfg'],
        'S' => ['VISIBLE_EN_CARTA', 18, 'cfg'],
        'T' => ['CONTROL_STOCK',    16, 'cfg'],
        'U' => ['VENTA_SIN_STOCK',  18, 'cfg'],
        'V' => ['VENDIBLE',         14, 'cfg'],
        'W' => ['ORDEN',            10, 'cfg'],
    ];

    private const GROUPS = [
        'info'  => ['INFORMACIÓN',  'A', 'L', self::COLOR_INFO],
        'venta' => ['VENTA',        'M', 'P', self::COLOR_VENTA],
        'var'   => ['VARIANTES',    'Q', 'Q', self::COLOR_VAR],
        'cfg'   => ['CONFIGURACIÓN','R', 'W', self::COLOR_CFG],
    ];

    // ── Hoja 2: Variantes ─────────────────────────────────────────────────────
    private const VAR_COLS = [
        'A' => ['CODIGO_INTERNO',  18],
        'B' => ['NOMBRE_PRODUCTO', 38],
        'C' => ['CODIGO_VARIANTE', 20],
        'D' => ['COD_BARRAS_VAR',  20],
        'E' => ['COMBINACION',     38],
        'F' => ['PRECIO_BASE',     16],
        'G' => ['PRECIO_EXTRA',    28],
        'H' => ['PRECIO_FINAL',    16],
        'I' => ['PRECIO_COSTO',    16],
        'J' => ['STOCK_REAL',      14],
        'K' => ['STOCK_MINIMO',    14],
        'L' => ['ESTADO',          14],
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
                'variantes' => fn ($q) => $q->where('estado', 'activo')
                    ->with([
                        'valores.valor',
                        'valores.productoAtributo.atributo',
                        'inventario',
                    ]),
            ])
            ->orderBy('nombre')
            ->get();

        $spreadsheet = new Spreadsheet();

        $this->buildProductosSheet($spreadsheet, $empresa, $productos);
        $this->buildVariantesSheet($spreadsheet, $empresa, $productos);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    // ── Hoja 1 ────────────────────────────────────────────────────────────────

    private function buildProductosSheet(Spreadsheet $spreadsheet, Empresa $empresa, $productos): void
    {
        $sheet   = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos');
        $lastCol = array_key_last(self::COLS);

        // Fila 1: empresa
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1',
            strtoupper($empresa->nombre) . ' — Exportado: ' . now()->format('d/m/Y H:i')
        );
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF' . self::COLOR_INFO]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Fila 2: grupos
        foreach (self::GROUPS as [$label, $from, $to, $color]) {
            $range = ($from === $to) ? "{$from}2" : "{$from}2:{$to}2";
            if ($from !== $to) $sheet->mergeCells("{$from}2:{$to}2");
            $sheet->setCellValue("{$from}2", $label);
            $sheet->getStyle($range)->applyFromArray([
                'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $color]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
            ]);
        }
        $sheet->getRowDimension(2)->setRowHeight(18);

        // Fila 3: columnas
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

        // Datos
        foreach ($productos as $i => $p) {
            $row      = $i + 4;
            $zebra    = ($i % 2 === 1) ? 'FF' . self::COLOR_ZEB : 'FFFFFFFF';
            $variantes = $p->variantes ?? collect();
            $tieneVar  = $variantes->isNotEmpty();

            $stockTotal = $tieneVar
                ? $variantes->sum(fn ($v) => (float) ($v->inventario?->stock_real ?? 0))
                : (float) ($p->inventario?->stock_real ?? 0);

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
            $sheet->setCellValue("M{$row}", $p->categoria?->nombre ?? '');
            $sheet->setCellValue("N{$row}", $p->marca?->nombre ?? '');
            $sheet->setCellValue("O{$row}", $p->produccion?->nombre ?? '');
            $sheet->setCellValue("P{$row}", $p->etiqueta?->getLabel() ?? '');
            $sheet->setCellValue("Q{$row}", $variantes->count());
            $sheet->setCellValue("R{$row}", $p->es_cortesia      ? 'Sí' : 'No');
            $sheet->setCellValue("S{$row}", $p->visible_en_carta ? 'Sí' : 'No');
            $sheet->setCellValue("T{$row}", $p->control_de_stock ? 'Sí' : 'No');
            $sheet->setCellValue("U{$row}", $p->venta_sin_stock  ? 'Sí' : 'No');
            $sheet->setCellValue("V{$row}", $p->vendible         ? 'Sí' : 'No');
            $sheet->setCellValue("W{$row}", (int) ($p->orden ?? 0));

            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'font'      => ['size' => 9],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $zebra]],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BRD]]],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
            ]);

            foreach (['B', 'C', 'D', 'E', 'H', 'I', 'Q', 'W'] as $nc) {
                $sheet->getStyle("{$nc}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            foreach (['G', 'R', 'S', 'T', 'U', 'V'] as $cc) {
                $sheet->getStyle("{$cc}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        // Fila total
        $totalRow = $productos->count() + 4;
        $sheet->mergeCells("A{$totalRow}:{$lastCol}{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'Total: ' . $productos->count() . ' productos exportados');
        $sheet->getStyle("A{$totalRow}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF' . self::COLOR_INFO]],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . self::COLOR_INFO]]],
        ]);

        $sheet->freezePane('A4');
    }

    // ── Hoja 2 ────────────────────────────────────────────────────────────────

    private function buildVariantesSheet(Spreadsheet $spreadsheet, Empresa $empresa, $productos): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Variantes');
        $lastCol = array_key_last(self::VAR_COLS);

        // Fila 1: empresa
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1',
            strtoupper($empresa->nombre) . ' — Variantes — Exportado: ' . now()->format('d/m/Y H:i')
        );
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FF' . self::COLOR_VAR]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEDE9FE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Fila 2: cabeceras
        foreach (self::VAR_COLS as $col => [$label, $width]) {
            $sheet->setCellValue("{$col}2", $label);
            $sheet->getStyle("{$col}2")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::COLOR_VAR]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
            ]);
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->getRowDimension(2)->setRowHeight(20);

        $row   = 3;
        $total = 0;

        foreach ($productos as $p) {
            $variantes = $p->variantes ?? collect();
            if ($variantes->isEmpty()) continue;

            foreach ($variantes as $vi => $v) {
                $zebra = ($vi % 2 === 1) ? 'FF' . self::COLOR_ZEB : 'FFFFFFFF';

                // Combinación: "Talla: S / Color: Rojo"
                $combinacion = $v->valores
                    ->map(function ($pav) {
                        $attr  = $pav->productoAtributo?->atributo?->nombre ?? '';
                        $valor = $pav->valor?->nombre ?? '';
                        return $attr ? "{$attr}: {$valor}" : $valor;
                    })
                    ->filter()
                    ->implode(' / ');

                $precioExtra    = $v->valores->sum(fn ($pav) => (float) ($pav->precio_adicional ?? 0));
                $precioExtraStr = $this->formatearPrecioExtra($v->valores);

                $sheet->setCellValueExplicit("A{$row}", $p->codigo_interno ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue("B{$row}", $p->nombre ?? '');
                $sheet->setCellValueExplicit("C{$row}", $v->codigo ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("D{$row}", $v->codigo_barras ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue("E{$row}", $combinacion ?: '—');
                $sheet->setCellValue("F{$row}", (float) ($p->precio_venta ?? 0));
                $sheet->setCellValue("G{$row}", $precioExtraStr);
                $sheet->setCellValue("H{$row}", (float) ($v->precio_final ?? 0));
                $sheet->setCellValue("I{$row}", (float) ($v->precio_costo ?? 0));
                $sheet->setCellValue("J{$row}", (float) ($v->inventario?->stock_real ?? 0));
                $sheet->setCellValue("K{$row}", (float) ($v->inventario?->stock_minimo ?? 0));
                $sheet->setCellValue("L{$row}", ucfirst($v->estado ?? ''));

                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'font'      => ['size' => 9],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $zebra]],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BRD]]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);

                foreach (['F', 'G', 'H', 'I', 'J', 'K'] as $nc) {
                    $sheet->getStyle("{$nc}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $sheet->getStyle("L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Precio extra en cero = celda gris para distinguir visualmente
                if ($precioExtra == 0) {
                    $sheet->getStyle("G{$row}")->applyFromArray([
                        'font' => ['color' => ['argb' => 'FFAAAAAA']],
                    ]);
                }

                $row++;
                $total++;
            }
        }

        // Fila total
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", 'Total: ' . $total . ' variantes exportadas');
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF' . self::COLOR_VAR]],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEDE9FE']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF' . self::COLOR_VAR]]],
        ]);

        $sheet->freezePane('A3');
    }

    /**
     * Formatea el PRECIO_EXTRA con el mismo formato que acepta el import:
     *   1 atributo  → "5"
     *   2+ atributos → "M:5 / Rojo:3"  (solo los valores con extra > 0)
     *   Sin extras   → "0"
     */
    private function formatearPrecioExtra($valores): string
    {
        $conExtras = $valores->filter(fn ($pav) => (float) ($pav->precio_adicional ?? 0) > 0);

        if ($conExtras->isEmpty()) {
            return '0';
        }

        // Un solo atributo en la combinación → número directo
        if ($valores->count() === 1) {
            return rtrim(rtrim(number_format((float) $conExtras->first()->precio_adicional, 4, '.', ''), '0'), '.');
        }

        // Varios atributos → "Valor:precio / Valor2:precio2"
        return $conExtras->map(function ($pav) {
            $nombre = $pav->valor?->nombre ?? '';
            $extra  = rtrim(rtrim(number_format((float) $pav->precio_adicional, 4, '.', ''), '0'), '.');
            return "{$nombre}:{$extra}";
        })->implode(' / ');
    }

    // ── Guardar en archivo (para jobs en cola) ────────────────────────────────

    public function guardar(Empresa $empresa, string $rutaAbsoluta): void
    {
        $spreadsheet = $this->buildSpreadsheet($empresa);
        (new Xlsx($spreadsheet))->save($rutaAbsoluta);
    }
}

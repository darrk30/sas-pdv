<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductoExcelTemplateService
{
    // ── Colores ───────────────────────────────────────────────────────────────
    private const COLOR_HEADER_REQ  = '1E3A5F'; // azul oscuro → requerida
    private const COLOR_HEADER_OPT  = '2D6A9F'; // azul medio  → opcional
    private const COLOR_HEADER_TEXT = 'FFFFFF';
    private const COLOR_EJEMPLO     = 'FFF9E6'; // amarillo pálido → fila ejemplo
    private const COLOR_VALIDACION  = 'F0F7FF'; // celeste muy claro → dropdown
    private const COLOR_FORMULA     = 'E8F5E9'; // verde claro → auto-calculado
    private const COLOR_BORDE       = 'CBD5E1';

    // ── Filas ─────────────────────────────────────────────────────────────────
    private const FILA_HEADER  = 1;
    private const FILA_EJEMPLO = 2;
    private const FILA_MAX     = 1002;

    // ── Definición de columnas ────────────────────────────────────────────────

    private const COLS_NUEVOS = [
        'A' => ['CODIGO_INTERNO',       18, false, null,                           'Déjalo vacío para auto-generarlo.'],
        'B' => ['NOMBRE (*)',            32, true,  null,                           'Requerido. Nombre del producto.'],
        'C' => ['PRECIO_VENTA (*)',      16, true,  null,                           'Requerido. Precio de venta. Usa punto decimal. Ej: 25.90'],
        'D' => ['PRECIO_COSTO',         16, false, null,                           'Opcional. Costo del producto.'],
        'E' => ['DESCUENTO',            12, false, null,                           'Porcentaje de descuento 0-100. Ej: 10 (= 10%).'],
        'F' => ['PRECIO_CON_DESCUENTO', 22, false, null,                           'Se calcula automáticamente. No editar.'],
        'G' => ['UNIDAD_MEDIDA',        16, false, null,                           "Nombre o símbolo de la unidad (sin distinción de mayúsculas).\nPor nombre: Unidad, Kilogramo, Litro, Metro...\nPor símbolo SUNAT: NIU, KGM, LTR, MTR...\nVacío = usa 'Unidad' por defecto."],
        'H' => ['ESTADO',               13, false, 'activo,inactivo,archivado',    'Selecciona de la lista. Por defecto: activo.'],
        'I' => ['STOCK_MINIMO',         14, false, null,                           'Stock mínimo para alerta de bajo inventario.'],
        'J' => ['STOCK_INICIAL',        14, false, null,                           'Stock de arranque al crear el producto.'],
        'K' => ['CODIGO_BARRAS',        18, false, null,                           'Código de barras (EAN-13, Code128, etc.).'],
        'L' => ['CATEGORIA',            18, false, null,                           'Nombre de la categoría. Se crea automáticamente si no existe.'],
        'M' => ['MARCA',                15, false, null,                           'Nombre de la marca. Se crea automáticamente si no existe.'],
        'N' => ['AREA_PRODUCCION',      20, false, null,                           'Nombre del área de producción. Debe existir previamente.'],
        'O' => ['ETIQUETA',             13, false, 'nuevo,agotado,combo,promo,oferta', 'Selecciona de la lista o deja vacío.'],
        'P' => ['CONTROL_STOCK',        15, false, 'TRUE,FALSE',                   'TRUE = controla stock. FALSE = no controla.'],
        'Q' => ['ORDEN',               10, false, null,                           'Orden de visualización en catálogo y PDV. Menor número = aparece primero. Default: 0.'],
        'R' => ['VENTA_SIN_STOCK',      17, false, 'TRUE,FALSE',                   'TRUE = permite vender aunque no haya stock. FALSE = bloquea. Default: FALSE.'],
        'S' => ['COMPARTE_STOCK',       17, false, 'TRUE,FALSE',                   'TRUE = las presentaciones/variantes comparten el mismo stock. FALSE = cada una tiene su propio stock. Default: FALSE.'],
    ];

    private const COLS_ACTUALIZAR = [
        'A' => ['CODIGO_INTERNO (*)',    20, true,  null,                                'Requerido. Identifica el producto a actualizar.'],
        'B' => ['NOMBRE',               32, false, null,                                'Dejar vacío para no cambiar.'],
        'C' => ['PRECIO_VENTA',         16, false, null,                                'Dejar vacío para no cambiar.'],
        'D' => ['PRECIO_COSTO',         16, false, null,                                'Ignorado al actualizar. El costo se gestiona vía compras o ajustes.'],
        'E' => ['DESCUENTO',            12, false, null,                                'Porcentaje 0-100. Dejar vacío para no cambiar.'],
        'F' => ['PRECIO_CON_DESCUENTO', 22, false, null,                                'Se calcula automáticamente. No editar.'],
        'G' => ['UNIDAD_MEDIDA',        16, false, null,                                "Nombre o símbolo de la unidad (sin distinción de mayúsculas).\nPor nombre: Unidad, Kilogramo, Litro, Metro...\nPor símbolo SUNAT: NIU, KGM, LTR, MTR...\nVacío = no cambia."],
        'H' => ['ESTADO',               13, false, 'activo,inactivo,archivado',         'Selecciona de la lista. Dejar vacío para no cambiar.'],
        'I' => ['STOCK_MINIMO',         14, false, null,                                'Dejar vacío para no cambiar.'],
        'J' => ['STOCK_INICIAL',        14, false, null,                                "Solo se aplica si el producto nunca tuvo stock registrado (stock = 0 y sin movimientos previos).\nVacío = no hacer nada."],
        'K' => ['CODIGO_BARRAS',        18, false, null,                                'Dejar vacío para no cambiar.'],
        'L' => ['CATEGORIA',            18, false, null,                                'Se crea si no existe.'],
        'M' => ['MARCA',                15, false, null,                                'Se crea si no existe.'],
        'N' => ['AREA_PRODUCCION',      20, false, null,                                'Debe existir en el sistema.'],
        'O' => ['ETIQUETA',             13, false, 'nuevo,agotado,combo,promo,oferta',  'Selecciona de la lista. Dejar vacío para no cambiar.'],
        'P' => ['CONTROL_STOCK',        15, false, 'TRUE,FALSE',                        'TRUE o FALSE. Dejar vacío para no cambiar.'],
        'Q' => ['ORDEN',               10, false, null,                                'Orden de visualización. Vacío = no cambia.'],
        'R' => ['VENTA_SIN_STOCK',      17, false, 'TRUE,FALSE',                        'TRUE = permite vender aunque no haya stock. FALSE = bloquea. Vacío = no cambia.'],
        'S' => ['COMPARTE_STOCK',       17, false, 'TRUE,FALSE',                        'TRUE = las presentaciones/variantes comparten el mismo stock. FALSE = cada una tiene su propio stock. Vacío = no cambia.'],
    ];

    private const COLS_VARIANTES = [
        'A' => ['CODIGO_PRODUCTO (*)', 20, true,  null, 'Requerido. Código del producto al que pertenece esta variante.'],
        'B' => ['CODIGO_VARIANTE',     20, false, null, 'Código propio de la variante. Se genera automáticamente si se deja vacío.'],
        'C' => ['COMBINACION (*)',    40, true,  null, "Requerido. Define los atributos y valores de la variante.\nFormato: ATRIBUTO:VALOR / ATRIBUTO2:VALOR2\nEjemplo: Talla:M / Color:Rojo\n\nSepara múltiples atributos con  /  (barra con espacios).\nEl nombre del atributo y el valor se crean si no existen."],
        'D' => ['PRECIO_EXTRA',       28, false, null, "Precio adicional por valor de atributo.\n\n• Si la combinación tiene UN solo atributo:\n  Escribe solo el número. Ej: 10\n  (se aplica al único valor)\n\n• Si la combinación tiene MÁS de un atributo:\n  Especifica a qué valor(es) con el formato:\n  Valor:precio\n  Ej:  M:5            (solo Talla:M recibe +5)\n  Ej:  M:5 / Rojo:3  (M recibe +5 y Rojo +3)\n\n  Los valores no mencionados quedan en 0."],
        'E' => ['PRECIO_COSTO',       15, false, null, 'Costo de esta variante. Vacío = usa el costo del producto.'],
        'F' => ['STOCK_INICIAL',      14, false, null, 'Stock de arranque para esta variante. Se aplica si el producto tiene control de stock y el stock actual es 0.'],
        'G' => ['CODIGO_BARRAS',      18, false, null, 'Código de barras específico de esta variante (EAN-13, Code128, etc.).'],
    ];

    private const COLS_VARIANTES_ACTUALIZAR = [
        'A' => ['CODIGO_PRODUCTO',  20, false, null, "Código del producto padre.\nNecesario solo si no proporcionas CODIGO_VARIANTE en col B."],
        'B' => ['CODIGO_VARIANTE',  20, false, null, "Código propio de la variante para identificarla directamente.\nSi lo proporcionas, col A y C pueden quedar vacíos."],
        'C' => ['COMBINACION',      40, false, null, "Necesario solo si no proporcionas CODIGO_VARIANTE.\nFormato: Talla:M / Color:Rojo\nVacío = no cambiar la combinación."],
        'D' => ['PRECIO_EXTRA',     28, false, null, "Precio adicional por atributo/valor. Vacío = no cambiar.\nFormatos: solo el número (1 atributo) o Valor:precio (varios).\nEj: 10   o   Talla:6   o   Talla:6 / Color:3"],
        'E' => ['PRECIO_COSTO',     15, false, null, 'Costo de la variante. Vacío = no cambiar.'],
        'F' => ['STOCK_INICIAL',    14, false, null, "Solo se aplica si la variante nunca tuvo stock registrado (stock = 0 y sin movimientos).\nVacío = no hacer nada."],
        'G' => ['CODIGO_BARRAS',    18, false, null, 'Código de barras de la variante. Vacío = no cambiar.'],
    ];

    private const COLS_PRECIOS = [
        'A' => ['CODIGO_INTERNO (*)',    20, true,  null,   'Requerido. Identifica el producto.'],
        'B' => ['PRECIO_VENTA',         16, false, null,   'Nuevo precio de venta. Vacío = no cambia.'],
        'C' => ['DESCUENTO',            12, false, null,   'Porcentaje 0-100. Vacío = no cambia.'],
        'D' => ['PRECIO_CON_DESCUENTO', 22, false, null,   'Se calcula automáticamente. No editar.'],
    ];

    private const COLS_VARIANTES_PRECIOS = [
        'A' => ['CODIGO_PRODUCTO',  20, false, null, "Código del producto padre.\nNecesario solo si no proporcionas CODIGO_VARIANTE en col B."],
        'B' => ['CODIGO_VARIANTE',  20, false, null, "Código propio de la variante para identificarla directamente.\nSi lo proporcionas, col A y C pueden quedar vacíos."],
        'C' => ['COMBINACION',      40, false, null, "Para identificar la variante cuando no hay CODIGO_VARIANTE.\nFormato: Talla:M / Color:Rojo"],
        'D' => ['PRECIO_EXTRA (*)', 28, true,  null, "Requerido. Precio adicional por valor de atributo.\nFormatos: solo el número (1 atributo) o Valor:precio (varios).\nEj: 10   o   Talla:6   o   Talla:6 / Color:3\nActualiza el atributo indicado y recalcula el precio final."],
    ];

    // ── Ejemplos (sin PRECIO_CON_DESCUENTO — lo pone la fórmula) ─────────────

    private const EJEMPLO_NUEVOS = [
        'A' => 'CAMISETA-001',
        'B' => 'Camiseta Polo Blanca',
        'C' => '25.90',
        'D' => '15.00',
        'E' => '10',
        'G' => 'NIU',
        'H' => 'activo',
        'I' => '5',
        'J' => '100',
        'K' => '7501234567890',
        'L' => 'Ropa',
        'M' => 'Sin Marca',
        'N' => '',
        'O' => 'nuevo',
        'P' => 'TRUE',
        'Q' => '1',
        'R' => 'FALSE',
        'S' => 'FALSE',
    ];

    private const EJEMPLO_ACTUALIZAR = [
        'A' => 'CAMISETA-001',
        'B' => '',
        'C' => '27.90',
        'E' => '',
        'G' => '',
        'H' => '',
        'I' => '10',
        'J' => '',
        'K' => '',
        'L' => '',
        'M' => '',
        'N' => '',
        'O' => 'promo',
        'P' => '',
        'Q' => '1',
        'R' => '',        // VENTA_SIN_STOCK
        'S' => '',        // COMPARTE_STOCK
    ];

    private const EJEMPLO_PRECIOS = [
        'A' => 'CAMISETA-001',
        'B' => '27.90',  // PRECIO_VENTA
        'C' => '5',      // DESCUENTO
        // D = PRECIO_CON_DESCUENTO (fórmula)
    ];

    // ── API pública ───────────────────────────────────────────────────────────

    public function generarPlantillaNuevos(): Spreadsheet
    {
        $spreadsheet = $this->construir('Productos', self::COLS_NUEVOS, self::EJEMPLO_NUEVOS);
        $this->agregarHojaVariantes($spreadsheet);
        return $spreadsheet;
    }

    public function generarPlantillaActualizar(): Spreadsheet
    {
        $spreadsheet = $this->construir('Actualizar Productos', self::COLS_ACTUALIZAR, self::EJEMPLO_ACTUALIZAR);
        $this->agregarHojaVariantesActualizar($spreadsheet);
        return $spreadsheet;
    }

    public function generarPlantillaPrecios(): Spreadsheet
    {
        $spreadsheet = $this->construir('Actualizar Precios', self::COLS_PRECIOS, self::EJEMPLO_PRECIOS);
        $this->agregarHojaVariantesPrecios($spreadsheet);
        return $spreadsheet;
    }

    // ── Hoja de variantes ────────────────────────────────────────────────────

    private function agregarHojaVariantes(Spreadsheet $spreadsheet): void
    {
        $sheet    = $spreadsheet->createSheet();
        $sheet->setTitle('Variantes');
        $columnas = self::COLS_VARIANTES;
        $ultima   = array_key_last($columnas);

        $this->escribirHeaders($sheet, $columnas);
        $this->aplicarComentarios($sheet, $columnas);
        $this->aplicarAnchos($sheet, $columnas);
        $this->congelarPrimeraFila($sheet);

        // Filas de ejemplo (varias para ilustrar el formato)
        // Ejemplos: 1 atributo → precio directo | 2 atributos → Valor:precio
        $ejemplos = [
            ['A' => 'PERFUME-001', 'B' => '',          'C' => 'Presentación:30ml',       'D' => '0',          'E' => '8.00',  'F' => '50', 'G' => ''],
            ['A' => 'PERFUME-001', 'B' => '',          'C' => 'Presentación:50ml',       'D' => '10',         'E' => '8.00',  'F' => '30', 'G' => ''],
            ['A' => 'PERFUME-001', 'B' => '',          'C' => 'Presentación:100ml',      'D' => '30',         'E' => '8.00',  'F' => '20', 'G' => ''],
            ['A' => 'CAMISETA-001','B' => 'CAM-S-BL',  'C' => 'Talla:S / Color:Blanco', 'D' => '',           'E' => '12.00', 'F' => '15', 'G' => '7501234000001'],
            ['A' => 'CAMISETA-001','B' => '',           'C' => 'Talla:M / Color:Blanco', 'D' => 'M:5',        'E' => '12.00', 'F' => '10', 'G' => ''],
            ['A' => 'CAMISETA-001','B' => '',           'C' => 'Talla:M / Color:Negro',  'D' => 'M:5 / Negro:3','E' => '12.00', 'F' => '8', 'G' => ''],
        ];

        foreach ($ejemplos as $i => $ej) {
            $fila = self::FILA_EJEMPLO + $i;
            foreach ($columnas as $col => $_) {
                if (isset($ej[$col])) {
                    $sheet->setCellValue($col . $fila, $ej[$col]);
                }
            }
            $sheet->getStyle('A' . $fila . ':' . $ultima . $fila)->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_EJEMPLO]],
                'font'    => ['italic' => true, 'size' => 11, 'color' => ['rgb' => '92400E']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_BORDE]]],
            ]);
        }

        $sheet->getComment('A' . self::FILA_EJEMPLO)
            ->getText()
            ->createTextRun('Estas son filas de EJEMPLO. Puedes modificarlas o eliminarlas.');
    }

    private function agregarHojaVariantesPrecios(Spreadsheet $spreadsheet): void
    {
        $sheet    = $spreadsheet->createSheet();
        $sheet->setTitle('Variantes');
        $columnas = self::COLS_VARIANTES_PRECIOS;
        $ultima   = array_key_last($columnas);

        $this->escribirHeaders($sheet, $columnas);
        $this->aplicarComentarios($sheet, $columnas);
        $this->aplicarAnchos($sheet, $columnas);
        $this->congelarPrimeraFila($sheet);

        // Ejemplos: identificar por código de variante o por producto+combinación
        $ejemplos = [
            ['A' => '',            'B' => 'PROD_2_001',   'C' => '',                       'D' => '5'],
            ['A' => 'PERFUME-001', 'B' => '',             'C' => 'Presentación:50ml',      'D' => '10'],
            ['A' => 'CAMISETA-001','B' => '',             'C' => 'Talla:M / Color:Blanco', 'D' => 'M:5'],
            ['A' => 'CAMISETA-001','B' => '',             'C' => 'Talla:M / Color:Negro',  'D' => 'M:5 / Negro:3'],
        ];

        foreach ($ejemplos as $i => $ej) {
            $fila = self::FILA_EJEMPLO + $i;
            foreach ($columnas as $col => $_) {
                if (isset($ej[$col])) {
                    $sheet->setCellValue($col . $fila, $ej[$col]);
                }
            }
            $sheet->getStyle('A' . $fila . ':' . $ultima . $fila)->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_EJEMPLO]],
                'font'    => ['italic' => true, 'size' => 11, 'color' => ['rgb' => '92400E']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_BORDE]]],
            ]);
        }

        $sheet->getComment('A' . self::FILA_EJEMPLO)
            ->getText()
            ->createTextRun("Estas son filas de EJEMPLO. Puedes modificarlas o eliminarlas.\nCol A: código de la variante (directo). Si está vacío, usa B + C.\nPRECIO_EXTRA actualiza el precio adicional por atributo/valor y recalcula el precio final.");
    }

    private function agregarHojaVariantesActualizar(Spreadsheet $spreadsheet): void
    {
        $sheet    = $spreadsheet->createSheet();
        $sheet->setTitle('Variantes');
        $columnas = self::COLS_VARIANTES_ACTUALIZAR;
        $ultima   = array_key_last($columnas);

        $this->escribirHeaders($sheet, $columnas);
        $this->aplicarComentarios($sheet, $columnas);
        $this->aplicarAnchos($sheet, $columnas);
        $this->congelarPrimeraFila($sheet);

        // Ejemplos representativos para actualizar variantes
        $ejemplos = [
            ['A' => '',            'B' => 'PROD_2_001',  'C' => '',                       'D' => '5',   'E' => '',     'F' => '',  'G' => ''],
            ['A' => 'CAMISETA-001','B' => '',            'C' => 'Talla:M / Color:Blanco', 'D' => 'M:5', 'E' => '12.00','F' => '',  'G' => ''],
            ['A' => 'CAMISETA-001','B' => '',            'C' => 'Talla:S / Color:Negro',  'D' => '',    'E' => '',     'F' => '20','G' => '7501234000099'],
        ];

        foreach ($ejemplos as $i => $ej) {
            $fila = self::FILA_EJEMPLO + $i;
            foreach ($columnas as $col => $_) {
                if (isset($ej[$col])) {
                    $sheet->setCellValue($col . $fila, $ej[$col]);
                }
            }
            $sheet->getStyle('A' . $fila . ':' . $ultima . $fila)->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_EJEMPLO]],
                'font'    => ['italic' => true, 'size' => 11, 'color' => ['rgb' => '92400E']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_BORDE]]],
            ]);
        }

        $sheet->getComment('B' . self::FILA_EJEMPLO)
            ->getText()
            ->createTextRun("Estas son filas de EJEMPLO. Puedes modificarlas o eliminarlas.\nCol B (CODIGO_VARIANTE): basta con este código para identificar la variante.\nSi no tienes el código, usa CODIGO_INTERNO + COMBINACION.");
    }

    // ── Construcción interna ──────────────────────────────────────────────────

    private function construir(string $titulo, array $columnas, array $ejemplo): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle($titulo);

        $ultimaCol = array_key_last($columnas);

        $this->escribirHeaders($sheet, $columnas);
        $this->escribirEjemplo($sheet, $columnas, $ejemplo, $ultimaCol);
        $this->aplicarValidaciones($sheet, $columnas);
        $this->aplicarComentarios($sheet, $columnas);
        $this->aplicarFormulas($sheet, $columnas, $ultimaCol);
        $this->aplicarAnchos($sheet, $columnas);
        $this->congelarPrimeraFila($sheet);

        return $spreadsheet;
    }

    private function escribirHeaders(Worksheet $sheet, array $columnas): void
    {
        foreach ($columnas as $col => [$header, , $requerida]) {
            $celda      = $col . self::FILA_HEADER;
            $colorFondo = $requerida ? self::COLOR_HEADER_REQ : self::COLOR_HEADER_OPT;

            $sheet->setCellValue($celda, $header);
            $sheet->getStyle($celda)->applyFromArray([
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => self::COLOR_HEADER_TEXT],
                    'size'  => 11,
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $colorFondo],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => false,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => self::COLOR_BORDE],
                    ],
                ],
            ]);
        }

        $sheet->getRowDimension(self::FILA_HEADER)->setRowHeight(24);
    }

    private function escribirEjemplo(Worksheet $sheet, array $columnas, array $ejemplo, string $ultimaCol): void
    {
        $fila = self::FILA_EJEMPLO;

        foreach ($columnas as $col => $_) {
            if (isset($ejemplo[$col])) {
                $sheet->setCellValue($col . $fila, $ejemplo[$col]);
            }
        }

        $rango = 'A' . $fila . ':' . $ultimaCol . $fila;
        $sheet->getStyle($rango)->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_EJEMPLO]],
            'font'    => ['italic' => true, 'size' => 11, 'color' => ['rgb' => '92400E']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::COLOR_BORDE]]],
        ]);

        $sheet->getComment('A' . $fila)
            ->getText()
            ->createTextRun('Esta es una fila de EJEMPLO. Puedes modificarla o eliminarla.');
    }

    private function aplicarValidaciones(Worksheet $sheet, array $columnas): void
    {
        $desde = self::FILA_EJEMPLO;
        $hasta = self::FILA_MAX;

        foreach ($columnas as $col => [, , , $dropdown]) {
            if (! $dropdown) continue;

            $rango      = "{$col}{$desde}:{$col}{$hasta}";
            // getDataValidation() solo acepta celda única; setSqref() define el rango
            // NO llamar setShowDropDown() — omitir el atributo hace que Excel muestre la flecha
            $validation = $sheet->getCell($col . $desde)->getDataValidation();
            $validation->setSqref($rango);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setAllowBlank(true);
            $validation->setFormula1('"' . $dropdown . '"');
            $validation->setShowErrorMessage(true);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setErrorTitle('Valor no válido');
            $validation->setError('Selecciona un valor de la lista desplegable.');
            $validation->setShowInputMessage(true);
            $validation->setPromptTitle('Selecciona una opción');
            $validation->setPrompt('Haz clic en la flecha para ver las opciones.');

            $sheet->getStyle($rango)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_VALIDACION]],
            ]);
        }
    }

    private function aplicarComentarios(Worksheet $sheet, array $columnas): void
    {
        foreach ($columnas as $col => [, , , , $nota]) {
            if (! $nota) continue;

            $comment = $sheet->getComment($col . self::FILA_HEADER);
            $comment->setVisible(false);
            $comment->setWidth('240pt');
            $comment->setHeight('80pt');
            $comment->getText()->createTextRun($nota)->getFont()->setSize(11);
        }
    }

    private function aplicarFormulas(Worksheet $sheet, array $columnas, string $ultimaCol): void
    {
        // Identificar columnas clave por el nombre (sin sufijos)
        $colPrecioVenta = null;
        $colDescuento   = null;
        $colPrecioDesc  = null;

        foreach ($columnas as $col => [$header]) {
            $clave = strtoupper(trim(preg_replace('/\s*\(\*\)\s*/', '', $header)));
            match ($clave) {
                'PRECIO_VENTA'         => $colPrecioVenta = $col,
                'DESCUENTO'            => $colDescuento   = $col,
                'PRECIO_CON_DESCUENTO' => $colPrecioDesc  = $col,
                default                => null,
            };
        }

        if (! $colPrecioDesc || ! $colPrecioVenta) return;

        $desde = self::FILA_EJEMPLO;
        $hasta = self::FILA_MAX;

        // 1. Desbloquear todas las celdas de datos para que el usuario pueda editarlas
        $sheet->getStyle("A{$desde}:{$ultimaCol}{$hasta}")
            ->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);

        // 2. Escribir fórmula fila por fila
        for ($row = $desde; $row <= $hasta; $row++) {
            $v = $colPrecioVenta . $row;
            $d = $colDescuento   ? $colDescuento . $row : null;

            $formula = $d
                ? "=IF(OR({$v}=\"\",{$v}=0),\"\",ROUND({$v}*(1-IF({$d}=\"\",0,{$d})/100),2))"
                : "=IF(OR({$v}=\"\",{$v}=0),\"\",ROUND({$v},2))";

            $sheet->setCellValue($colPrecioDesc . $row, $formula);
        }

        // 3. Bloquear y colorear la columna calculada
        $rangoFormula = "{$colPrecioDesc}{$desde}:{$colPrecioDesc}{$hasta}";
        $sheet->getStyle($rangoFormula)->getProtection()
            ->setLocked(Protection::PROTECTION_PROTECTED);
        $sheet->getStyle($rangoFormula)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_FORMULA]],
        ]);

        // 4. Activar protección de hoja — solo bloquea la columna de fórmula
        $protection = $sheet->getProtection();
        $protection->setSheet(true);
        $protection->setPassword('');
        $protection->setSort(true);
        $protection->setInsertRows(true);
        $protection->setDeleteRows(true);
    }

    private function aplicarAnchos(Worksheet $sheet, array $columnas): void
    {
        foreach ($columnas as $col => [, $width]) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
    }

    private function congelarPrimeraFila(Worksheet $sheet): void
    {
        $sheet->freezePane('A2');
    }
}

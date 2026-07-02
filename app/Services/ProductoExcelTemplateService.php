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
        'G' => ['UNIDAD_MEDIDA',        16, false, null,                           "Escribe el SÍMBOLO de la unidad de medida.\nEjemplos: und  kg  lt  m  doc  caja\n(No el nombre completo, solo el símbolo)"],
        'H' => ['ESTADO',               13, false, 'activo,inactivo,archivado',    'Selecciona de la lista. Por defecto: activo.'],
        'I' => ['STOCK_MINIMO',         14, false, null,                           'Stock mínimo para alerta de bajo inventario.'],
        'J' => ['STOCK_INICIAL',        14, false, null,                           'Stock de arranque al crear el producto.'],
        'K' => ['CODIGO_BARRAS',        18, false, null,                           'Código de barras (EAN-13, Code128, etc.).'],
        'L' => ['CATEGORIA',            18, false, null,                           'Nombre de la categoría. Se crea automáticamente si no existe.'],
        'M' => ['MARCA',                15, false, null,                           'Nombre de la marca. Se crea automáticamente si no existe.'],
        'N' => ['AREA_PRODUCCION',      20, false, null,                           'Nombre del área de producción. Debe existir previamente.'],
        'O' => ['ETIQUETA',             13, false, 'nuevo,agotado,combo,promo,oferta', 'Selecciona de la lista o deja vacío.'],
        'P' => ['CONTROL_STOCK',        15, false, 'TRUE,FALSE',                   'TRUE = controla stock. FALSE = no controla.'],
    ];

    private const COLS_ACTUALIZAR = [
        'A' => ['CODIGO_INTERNO (*)',    20, true,  null,                           'Requerido. Identifica el producto a actualizar.'],
        'B' => ['NOMBRE',               32, false, null,                           'Dejar vacío para no cambiar.'],
        'C' => ['PRECIO_VENTA',         16, false, null,                           'Dejar vacío para no cambiar.'],
        'D' => ['DESCUENTO',            12, false, null,                           'Porcentaje 0-100. Dejar vacío para no cambiar.'],
        'E' => ['PRECIO_CON_DESCUENTO', 22, false, null,                           'Se calcula automáticamente. No editar.'],
        'F' => ['UNIDAD_MEDIDA',        16, false, null,                           "Escribe el SÍMBOLO de la unidad.\nEjemplos: und  kg  lt  m  doc  caja\nDejar vacío para no cambiar."],
        'G' => ['ESTADO',               13, false, 'activo,inactivo,archivado',    'Selecciona de la lista. Dejar vacío para no cambiar.'],
        'H' => ['STOCK_MINIMO',         14, false, null,                           'Dejar vacío para no cambiar.'],
        'I' => ['CODIGO_BARRAS',        18, false, null,                           'Dejar vacío para no cambiar.'],
        'J' => ['CATEGORIA',            18, false, null,                           'Se crea si no existe.'],
        'K' => ['MARCA',                15, false, null,                           'Se crea si no existe.'],
        'L' => ['AREA_PRODUCCION',      20, false, null,                           'Debe existir en el sistema.'],
        'M' => ['ETIQUETA',             13, false, 'nuevo,agotado,combo,promo,oferta', 'Selecciona de la lista. Dejar vacío para no cambiar.'],
        'N' => ['CONTROL_STOCK',        15, false, 'TRUE,FALSE',                   'TRUE o FALSE. Dejar vacío para no cambiar.'],
    ];

    private const COLS_PRECIOS = [
        'A' => ['CODIGO_INTERNO (*)',    20, true,  null,   'Requerido. Identifica el producto.'],
        'B' => ['PRECIO_VENTA',         16, false, null,   'Nuevo precio de venta. Vacío = no cambia.'],
        'C' => ['DESCUENTO',            12, false, null,   'Porcentaje 0-100. Vacío = no cambia.'],
        'D' => ['PRECIO_CON_DESCUENTO', 22, false, null,   'Se calcula automáticamente. No editar.'],
    ];

    // ── Ejemplos (sin PRECIO_CON_DESCUENTO — lo pone la fórmula) ─────────────

    private const EJEMPLO_NUEVOS = [
        'A' => 'CAMISETA-001',
        'B' => 'Camiseta Polo Blanca',
        'C' => '25.90',
        'D' => '15.00',
        'E' => '10',
        'G' => 'und',
        'H' => 'activo',
        'I' => '5',
        'J' => '100',
        'K' => '7501234567890',
        'L' => 'Ropa',
        'M' => 'Sin Marca',
        'N' => '',
        'O' => 'nuevo',
        'P' => 'TRUE',
    ];

    private const EJEMPLO_ACTUALIZAR = [
        'A' => 'CAMISETA-001',
        'B' => '',
        'C' => '27.90',  // PRECIO_VENTA
        'D' => '',        // DESCUENTO
        // E = PRECIO_CON_DESCUENTO (fórmula)
        'F' => '',        // UNIDAD_MEDIDA
        'G' => '',        // ESTADO
        'H' => '10',     // STOCK_MINIMO
        'I' => '',        // CODIGO_BARRAS
        'J' => '',        // CATEGORIA
        'K' => '',        // MARCA
        'L' => '',        // AREA_PRODUCCION
        'M' => 'promo',  // ETIQUETA
        'N' => '',        // CONTROL_STOCK
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
        return $this->construir('Importar Nuevos Productos', self::COLS_NUEVOS, self::EJEMPLO_NUEVOS);
    }

    public function generarPlantillaActualizar(): Spreadsheet
    {
        return $this->construir('Actualizar Productos', self::COLS_ACTUALIZAR, self::EJEMPLO_ACTUALIZAR);
    }

    public function generarPlantillaPrecios(): Spreadsheet
    {
        return $this->construir('Actualizar Precios', self::COLS_PRECIOS, self::EJEMPLO_PRECIOS);
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

        // 4. Activar protección de hoja (sin contraseña — solo previene edición accidental)
        $protection = $sheet->getProtection();
        $protection->setSheet(true);
        $protection->setPassword('');
        $protection->setSort(false);
        $protection->setInsertRows(false);
        $protection->setDeleteRows(false);
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

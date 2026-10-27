<?php

namespace App\Services;

use App\Enums\EstadoGeneral;
use App\Enums\ProductoEtiqueta;
use App\Models\Atributo;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Marca;
use App\Models\Produccion;
use App\Models\Producto;
use App\Models\ProductoAtributo;
use App\Models\ProductoAtributoValor;
use App\Models\UnidadesMedida;
use App\Models\Valor;
use App\Models\Variante;
use App\Models\VarianteValor;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductoImportService
{
    public function __construct(private readonly KardexService $kardex) {}

    private int $empresaId;

    // ── Resultado de la importación ───────────────────────────────────────────

    private int   $creados           = 0;
    private int   $actualizados      = 0;
    private int   $omitidos          = 0;
    private int   $variantesCreadas  = 0;
    private int   $variantesActualizadas = 0;
    private array $errores           = [];

    // ── Catálogos cargados una vez para toda la importación ───────────────────

    private array $categoriasCache         = [];
    private array $marcasCache             = [];
    private array $unidadesCache           = [];
    private array $produccionesCache       = [];
    private array $atributosCache          = [];
    private array $valoresCache            = [];
    private array $prodAtributosCache      = [];
    private array $prodAtribValoresCache   = [];

    // ── API pública ───────────────────────────────────────────────────────────

    /**
     * Importar productos nuevos.
     * Columnas: CODIGO_INTERNO, NOMBRE, PRECIO_VENTA, PRECIO_COSTO, DESCUENTO,
     *           PRECIO_CON_DESCUENTO, UNIDAD_MEDIDA, ESTADO, STOCK_MINIMO,
     *           STOCK_INICIAL, CODIGO_BARRAS, CATEGORIA, MARCA, AREA_PRODUCCION,
     *           ETIQUETA, CONTROL_STOCK
     */
    public function importarNuevos(string $ruta, int $empresaId): array
    {
        $this->inicializar($empresaId);
        $spreadsheet = IOFactory::load($ruta);
        $filas       = $this->leerHoja($spreadsheet->getActiveSheet());

        foreach ($filas as $num => $fila) {
            try {
                $nombre = trim($fila['B'] ?? '');
                if ($nombre === '') {
                    $this->omitidos++;
                    continue;
                }

                $codigoInterno = trim($fila['A'] ?? '');

                // Verificar duplicado por codigo_interno
                if ($codigoInterno !== '') {
                    $existe = Producto::where('empresa_id', $empresaId)
                        ->where('codigo_interno', $codigoInterno)
                        ->exists();

                    if ($existe) {
                        $this->errores[] = "Fila {$num}: código interno '{$codigoInterno}' ya existe. Usa 'Actualizar' para modificarlo.";
                        $this->omitidos++;
                        continue;
                    }
                }

                $precioVenta = $this->parseDecimal($fila['C'] ?? null);
                if ($precioVenta === null) {
                    $this->errores[] = "Fila {$num}: falta el PRECIO_VENTA.";
                    $this->omitidos++;
                    continue;
                }

                $precioCosto       = $this->parseDecimal($fila['D'] ?? null) ?? 0;
                $stockMinimo       = $this->parseDecimal($fila['I'] ?? null) ?? 0;
                $stockInicial      = $this->parseDecimal($fila['J'] ?? null) ?? 0;
                $controlDeStock    = $this->parseBooleano($fila['P'] ?? null, true);
                $ventaSinStock     = $this->parseBooleano($fila['R'] ?? null, false);
                $comparteStock     = $this->parseBooleano($fila['S'] ?? null, false);
                $descuento         = $this->parseDecimal($fila['E'] ?? null) ?? 0;
                $precioConDesc     = $this->parseDecimal($fila['F'] ?? null)
                    ?? round($precioVenta * (1 - $descuento / 100), 4);

                $producto = Producto::create([
                    'empresa_id'            => $empresaId,
                    'codigo_interno'        => $codigoInterno !== '' ? $codigoInterno : $this->generarCodigo($empresaId),
                    'nombre'                => $nombre,
                    'slug'                  => $this->generarSlug($nombre, $empresaId),
                    'precio_venta'          => $precioVenta,
                    'precio_costo'          => $precioCosto,
                    'porcentaje_descuento'  => $descuento,
                    'precio_con_descuento'  => $precioConDesc,
                    'unidad_medida_id'      => $this->resolverUnidad($fila['G'] ?? null),
                    'estado'                => $this->resolverEstado($fila['H'] ?? null),
                    'codigo_barras'         => trim($fila['K'] ?? '') ?: null,
                    'categoria_id'          => $this->resolverCategoria($fila['L'] ?? null),
                    'marca_id'              => $this->resolverMarca($fila['M'] ?? null),
                    'produccion_id'         => $this->resolverProduccion($fila['N'] ?? null),
                    'etiqueta'              => $this->resolverEtiqueta($fila['O'] ?? null),
                    'control_de_stock'      => $controlDeStock,
                    'orden'                 => (int) ($this->parseDecimal($fila['Q'] ?? null) ?? 0),
                    'visible_en_carta'      => true,
                    'es_cortesia'           => false,
                    'venta_sin_stock'       => $ventaSinStock,
                    'comparte_stock'        => $comparteStock,
                ]);

                if ($controlDeStock) {
                    Inventario::create([
                        'empresa_id'        => $empresaId,
                        'producto_id'       => $producto->id,
                        'variante_id'       => null,
                        'stock_real'        => $stockInicial,
                        'stock_reserva'     => $stockInicial,
                        'stock_minimo'      => $stockMinimo,
                        'estado_almacen'    => 'activo',
                        'estado_inventario' => $stockInicial > 0 ? 'disponible' : 'agotado',
                    ]);

                    if ($stockInicial > 0) {
                        $this->kardex->registrar([
                            'empresa_id'      => $empresaId,
                            'user_id'         => auth()->id(),
                            'producto_id'     => $producto->id,
                            'producto_nombre' => $producto->nombre,
                            'tipo'            => 'entrada',
                            'concepto'        => 'Importación masiva',
                            'cantidad'        => $stockInicial,
                            'cantidad_base'   => $stockInicial,
                            'costo_unitario'  => $precioCosto ?: null,
                            'costo_total'     => $precioCosto > 0 ? round($precioCosto * $stockInicial, 4) : null,
                            'stock_antes'     => 0,
                            'stock_despues'   => $stockInicial,
                            'fecha'           => now(),
                        ]);
                    }
                }

                $this->creados++;
            } catch (\Illuminate\Database\QueryException $e) {
                $this->errores[] = "Fila {$num}: error al guardar el producto '{$nombre}' — " . $this->mensajeDb($e);
                $this->omitidos++;
            } catch (\Throwable $e) {
                $this->errores[] = "Fila {$num}: " . $e->getMessage();
                $this->omitidos++;
            }
        }

        // Procesar hoja Variantes si existe
        $hojaVariantes = $spreadsheet->getSheetByName('Variantes');
        if ($hojaVariantes !== null) {
            $this->procesarVariantesSheet($hojaVariantes);
        }

        return $this->resultado();
    }

    /**
     * Actualizar productos existentes por CODIGO_INTERNO.
     * Columnas: CODIGO_INTERNO, NOMBRE, PRECIO_VENTA, PRECIO_COSTO, DESCUENTO,
     *           PRECIO_CON_DESCUENTO, UNIDAD_MEDIDA, ESTADO, STOCK_MINIMO,
     *           CODIGO_BARRAS, CATEGORIA, MARCA, AREA_PRODUCCION, ETIQUETA, CONTROL_STOCK
     */
    public function importarActualizar(string $ruta, int $empresaId): array
    {
        $this->inicializar($empresaId);
        $filas = $this->leerExcel($ruta);

        foreach ($filas as $num => $fila) {
            try {
                $codigoInterno = trim($fila['A'] ?? '');
                if ($codigoInterno === '') {
                    $this->omitidos++;
                    continue;
                }

                $producto = Producto::where('empresa_id', $empresaId)
                    ->where('codigo_interno', $codigoInterno)
                    ->first();

                if (! $producto) {
                    $this->errores[] = "Fila {$num}: código '{$codigoInterno}' no encontrado.";
                    $this->omitidos++;
                    continue;
                }

                $cambios = [];

                // Mismo layout que importarNuevos (A–P) para que el mismo archivo funcione en ambos modos.
                // D=PRECIO_COSTO y J=STOCK_INICIAL se ignoran (el costo y stock se gestionan via compras/ajustes).
                // A=CODIGO_INTERNO, B=NOMBRE, C=PRECIO_VENTA, D=(ignorado), E=DESCUENTO,
                // F=PRECIO_CON_DESCUENTO, G=UNIDAD_MEDIDA, H=ESTADO, I=STOCK_MINIMO,
                // J=(ignorado), K=CODIGO_BARRAS, L=CATEGORIA, M=MARCA,
                // N=AREA_PRODUCCION, O=ETIQUETA, P=CONTROL_STOCK
                $this->asignarSiNoVacio($cambios, 'nombre',                      $fila['B'] ?? null);
                $this->asignarDecimalSiNoVacio($cambios, 'precio_venta',         $fila['C'] ?? null);
                $this->asignarDecimalSiNoVacio($cambios, 'porcentaje_descuento', $fila['E'] ?? null);
                $this->asignarDecimalSiNoVacio($cambios, 'precio_con_descuento', $fila['F'] ?? null);
                $this->asignarSiNoVacio($cambios, 'codigo_barras',               $fila['K'] ?? null);

                $unidadId = $this->resolverUnidad($fila['G'] ?? null);
                if ($unidadId !== null) $cambios['unidad_medida_id'] = $unidadId;

                $estado = $this->resolverEstado($fila['H'] ?? null);
                if ($estado !== null) $cambios['estado'] = $estado;

                // stock_minimo va en inventarios (columna I)
                $stockMinimo = $this->parseDecimal($fila['I'] ?? null);
                if ($stockMinimo !== null) {
                    Inventario::where('empresa_id', $empresaId)
                        ->where('producto_id', $producto->id)
                        ->whereNull('variante_id')
                        ->update(['stock_minimo' => $stockMinimo]);
                }

                $categoriaId = $this->resolverCategoria($fila['L'] ?? null);
                if ($categoriaId !== null) $cambios['categoria_id'] = $categoriaId;

                $marcaId = $this->resolverMarca($fila['M'] ?? null);
                if ($marcaId !== null) $cambios['marca_id'] = $marcaId;

                $produccionId = $this->resolverProduccion($fila['N'] ?? null);
                if ($produccionId !== null) $cambios['produccion_id'] = $produccionId;

                $etiqueta = $this->resolverEtiqueta($fila['O'] ?? null);
                if ($etiqueta !== null) $cambios['etiqueta'] = $etiqueta;

                $rawBool = trim($fila['P'] ?? '');
                if ($rawBool !== '') {
                    $cambios['control_de_stock'] = $this->parseBooleano($rawBool, true);
                }

                $orden = $this->parseDecimal($fila['Q'] ?? null);
                if ($orden !== null) {
                    $cambios['orden'] = (int) $orden;
                }

                // R = VENTA_SIN_STOCK
                $rawVentaSinStock = trim($fila['R'] ?? '');
                if ($rawVentaSinStock !== '') {
                    $cambios['venta_sin_stock'] = $this->parseBooleano($rawVentaSinStock, false);
                }

                // S = COMPARTE_STOCK
                $rawComparteStock = trim($fila['S'] ?? '');
                if ($rawComparteStock !== '') {
                    $cambios['comparte_stock'] = $this->parseBooleano($rawComparteStock, false);
                }

                // Regenerar slug si cambió el nombre
                if (isset($cambios['nombre'])) {
                    $cambios['slug'] = $this->generarSlug($cambios['nombre'], $empresaId, $producto->id);
                }

                if (! empty($cambios)) {
                    $producto->update($cambios);
                }

                // J = STOCK_INICIAL — solo si el producto nunca tuvo stock registrado
                $stockInicialRaw = $this->parseDecimal($fila['J'] ?? null);
                if ($stockInicialRaw !== null && $stockInicialRaw > 0 && $producto->control_de_stock) {
                    $inventario = Inventario::where('empresa_id', $empresaId)
                        ->where('producto_id', $producto->id)
                        ->whereNull('variante_id')
                        ->first();

                    $sinMovimientos = ! \App\Models\Kardex::where('empresa_id', $empresaId)
                        ->where('producto_id', $producto->id)
                        ->exists();

                    $stockActual = (float) ($inventario?->stock_real ?? 0);

                    if ($sinMovimientos && $stockActual == 0) {
                        if ($inventario) {
                            $inventario->update([
                                'stock_real'        => $stockInicialRaw,
                                'stock_reserva'     => $stockInicialRaw,
                                'estado_inventario' => 'disponible',
                            ]);
                        } else {
                            Inventario::create([
                                'empresa_id'        => $empresaId,
                                'producto_id'       => $producto->id,
                                'variante_id'       => null,
                                'stock_real'        => $stockInicialRaw,
                                'stock_reserva'     => $stockInicialRaw,
                                'stock_minimo'      => 0,
                                'estado_almacen'    => 'activo',
                                'estado_inventario' => 'disponible',
                            ]);
                        }

                        $this->kardex->registrar([
                            'empresa_id'      => $empresaId,
                            'user_id'         => auth()->id(),
                            'producto_id'     => $producto->id,
                            'producto_nombre' => $producto->nombre,
                            'tipo'            => 'entrada',
                            'concepto'        => 'Stock inicial (importación masiva)',
                            'cantidad'        => $stockInicialRaw,
                            'cantidad_base'   => $stockInicialRaw,
                            'costo_unitario'  => null,
                            'costo_total'     => null,
                            'stock_antes'     => 0,
                            'stock_despues'   => $stockInicialRaw,
                            'fecha'           => now(),
                        ]);
                    }
                }

                $this->actualizados++;
            } catch (\Illuminate\Database\QueryException $e) {
                $this->errores[] = "Fila {$num}: error al actualizar '{$codigoInterno}' — " . $this->mensajeDb($e);
                $this->omitidos++;
            } catch (\Throwable $e) {
                $this->errores[] = "Fila {$num}: " . $e->getMessage();
                $this->omitidos++;
            }
        }

        return $this->resultado();
    }

    /**
     * Actualizar solo precios por CODIGO_INTERNO.
     * Columnas: CODIGO_INTERNO, PRECIO_VENTA, PRECIO_COSTO, DESCUENTO, PRECIO_CON_DESCUENTO
     */
    public function importarPrecios(string $ruta, int $empresaId): array
    {
        $this->inicializar($empresaId);
        $filas = $this->leerExcel($ruta);

        foreach ($filas as $num => $fila) {
            try {
                $codigoInterno = trim($fila['A'] ?? '');
                if ($codigoInterno === '') {
                    $this->omitidos++;
                    continue;
                }

                $producto = Producto::where('empresa_id', $empresaId)
                    ->where('codigo_interno', $codigoInterno)
                    ->first();

                if (! $producto) {
                    $this->errores[] = "Fila {$num}: código '{$codigoInterno}' no encontrado.";
                    $this->omitidos++;
                    continue;
                }

                // A=CODIGO_INTERNO, B=PRECIO_VENTA, C=DESCUENTO, D=PRECIO_CON_DESCUENTO
                // precio_costo NO se actualiza (costo promedio = solo vía compras/ajustes)
                $cambios = [];
                $this->asignarDecimalSiNoVacio($cambios, 'precio_venta',         $fila['B'] ?? null);
                $this->asignarDecimalSiNoVacio($cambios, 'porcentaje_descuento', $fila['C'] ?? null);
                $this->asignarDecimalSiNoVacio($cambios, 'precio_con_descuento', $fila['D'] ?? null);

                if (! empty($cambios)) {
                    $producto->update($cambios);
                }

                $this->actualizados++;
            } catch (\Illuminate\Database\QueryException $e) {
                $this->errores[] = "Fila {$num}: error al actualizar precios de '{$codigoInterno}' — " . $this->mensajeDb($e);
                $this->omitidos++;
            } catch (\Throwable $e) {
                $this->errores[] = "Fila {$num}: " . $e->getMessage();
                $this->omitidos++;
            }
        }

        return $this->resultado();
    }

    // ── Lector de Excel ───────────────────────────────────────────────────────

    private function leerExcel(string $ruta): array
    {
        return $this->leerHoja(IOFactory::load($ruta)->getActiveSheet());
    }

    /**
     * Devuelve filas indexadas por letra de columna (A–Z).
     * Omite la fila 1 (headers). Detiene al encontrar una fila completamente vacía.
     */
    private function leerHoja(Worksheet $sheet): array
    {
        $filas = [];

        foreach ($sheet->getRowIterator(2) as $row) {
            $num  = $row->getRowIndex();
            $data = [];

            foreach ($row->getCellIterator('A', 'Z') as $cell) {
                $col   = $cell->getColumn();
                $valor = $cell->isFormula()
                    ? $cell->getCalculatedValue()
                    : $cell->getValue();

                if (is_float($valor) && XlDate::isDateTime($cell)) {
                    $valor = XlDate::excelToDateTimeObject($valor)->format('Y-m-d');
                }

                $data[$col] = $valor !== null ? (string) $valor : '';
            }

            $contenido = array_filter(array_values($data), fn($v) => trim($v) !== '');
            if (empty($contenido)) continue;

            $filas[$num] = $data;
        }

        return $filas;
    }

    // ── Inicialización ────────────────────────────────────────────────────────

    private function inicializar(int $empresaId): void
    {
        $this->empresaId              = $empresaId;
        $this->creados                = 0;
        $this->actualizados           = 0;
        $this->omitidos               = 0;
        $this->variantesCreadas       = 0;
        $this->variantesActualizadas  = 0;
        $this->errores                = [];
        $this->categoriasCache        = [];
        $this->marcasCache            = [];
        $this->unidadesCache          = [];
        $this->produccionesCache      = [];
        $this->atributosCache         = [];
        $this->valoresCache           = [];
        $this->prodAtributosCache     = [];
        $this->prodAtribValoresCache  = [];
        $this->unidadDefaultId        = null;
    }

    private function resultado(): array
    {
        return [
            'creados'               => $this->creados,
            'actualizados'          => $this->actualizados,
            'omitidos'              => $this->omitidos,
            'variantes_creadas'     => $this->variantesCreadas,
            'variantes_actualizadas'=> $this->variantesActualizadas,
            'errores'               => $this->errores,
        ];
    }

    // ── Resolvers de relaciones ───────────────────────────────────────────────

    private function resolverCategoria(?string $valor): ?int
    {
        $nombre = trim($valor ?? '');
        if ($nombre === '') return null;

        if (! isset($this->categoriasCache[$nombre])) {
            $cat = Categoria::firstOrCreate(
                ['empresa_id' => $this->empresaId, 'nombre' => $nombre],
                ['estado' => 1]
            );
            $this->categoriasCache[$nombre] = $cat->id;
        }

        return $this->categoriasCache[$nombre];
    }

    private function resolverMarca(?string $valor): ?int
    {
        $nombre = trim($valor ?? '');
        if ($nombre === '') return null;

        if (! isset($this->marcasCache[$nombre])) {
            $marca = Marca::firstOrCreate(
                ['empresa_id' => $this->empresaId, 'nombre' => $nombre],
                ['estado' => true]
            );
            $this->marcasCache[$nombre] = $marca->id;
        }

        return $this->marcasCache[$nombre];
    }

    private ?int $unidadDefaultId = null;

    private function unidadDefault(): ?int
    {
        if ($this->unidadDefaultId === null) {
            $this->unidadDefaultId = UnidadesMedida::where('empresa_id', $this->empresaId)
                ->value('id');
        }
        return $this->unidadDefaultId;
    }

    private function resolverUnidad(?string $valor): ?int
    {
        $raw = trim($valor ?? '');

        // Celda vacía → usar unidad por defecto
        if ($raw === '') return $this->unidadDefault();

        // Clave normalizada para el caché (todo minúscula, sin espacios extras)
        $key = mb_strtolower($raw);

        if (! isset($this->unidadesCache[$key])) {
            // Buscar por nombre O símbolo, sin distinguir mayúsculas/minúsculas
            $unidad = UnidadesMedida::where('empresa_id', $this->empresaId)
                ->where(fn($q) => $q
                    ->whereRaw('LOWER(nombre)  = ?', [$key])
                    ->orWhereRaw('LOWER(simbolo) = ?', [$key])
                )
                ->first();

            if (! $unidad) {
                $this->errores[] = "Advertencia: unidad '{$raw}' no encontrada. Se usó la unidad por defecto.";
                $this->unidadesCache[$key] = $this->unidadDefault();
            } else {
                $this->unidadesCache[$key] = $unidad->id;
            }
        }

        return $this->unidadesCache[$key];
    }

    private function resolverProduccion(?string $valor): ?int
    {
        $nombre = trim($valor ?? '');
        if ($nombre === '') return null;

        $key = mb_strtolower($nombre);
        if (! isset($this->produccionesCache[$key])) {
            $prod = Produccion::where('empresa_id', $this->empresaId)
                ->where('nombre', $nombre)
                ->first();
            $this->produccionesCache[$key] = $prod?->id;
        }

        return $this->produccionesCache[$key];
    }

    private function resolverEstado(?string $valor): ?string
    {
        $v = mb_strtolower(trim($valor ?? ''));
        if ($v === '') return EstadoGeneral::Activo->value;

        $validos = [
            EstadoGeneral::Activo->value,
            EstadoGeneral::Inactivo->value,
            EstadoGeneral::Archivado->value,
        ];

        return in_array($v, $validos, true) ? $v : EstadoGeneral::Activo->value;
    }

    private function resolverEtiqueta(?string $valor): ?string
    {
        $v = mb_strtolower(trim($valor ?? ''));
        if ($v === '') return null;

        $validos = array_column(ProductoEtiqueta::cases(), 'value');
        return in_array($v, $validos, true) ? $v : null;
    }

    // ── Parsers ───────────────────────────────────────────────────────────────

    private function parseDecimal(?string $valor): ?float
    {
        if ($valor === null || trim($valor) === '') return null;
        $limpio = str_replace(',', '.', trim($valor));
        return is_numeric($limpio) ? (float) $limpio : null;
    }

    private function parseBooleano(?string $valor, bool $default = true): bool
    {
        $v = mb_strtolower(trim($valor ?? ''));
        if ($v === '') return $default;
        return in_array($v, ['true', '1', 'si', 'sí', 'yes'], true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function generarCodigo(int $empresaId): string
    {
        do {
            $codigo = 'P-' . strtoupper(Str::random(6));
        } while (Producto::where('empresa_id', $empresaId)->where('codigo_interno', $codigo)->exists());

        return $codigo;
    }

    private function generarSlug(string $nombre, int $empresaId, ?int $exceptoId = null): string
    {
        $base  = Str::slug($nombre);
        $slug  = $base;
        $i     = 1;
        $query = Producto::where('empresa_id', $empresaId)->where('slug', $slug);
        if ($exceptoId) $query->where('id', '!=', $exceptoId);

        while ($query->exists()) {
            $slug  = $base . '-' . $i++;
            $query = Producto::where('empresa_id', $empresaId)->where('slug', $slug);
            if ($exceptoId) $query->where('id', '!=', $exceptoId);
        }

        return $slug;
    }

    private function asignarSiNoVacio(array &$cambios, string $campo, ?string $valor): void
    {
        if ($valor !== null && trim($valor) !== '') {
            $cambios[$campo] = trim($valor);
        }
    }

    private function asignarDecimalSiNoVacio(array &$cambios, string $campo, ?string $valor): void
    {
        $dec = $this->parseDecimal($valor);
        if ($dec !== null) {
            $cambios[$campo] = $dec;
        }
    }

    // ── Hoja Variantes ────────────────────────────────────────────────────────

    /**
     * Columnas: A=CODIGO_INTERNO, B=CODIGO_VARIANTE, C=COMBINACION,
     *           D=PRECIO_EXTRA, E=PRECIO_COSTO, F=STOCK_INICIAL, G=CODIGO_BARRAS
     */
    private function procesarVariantesSheet(Worksheet $sheet): void
    {
        $filas             = $this->leerHoja($sheet);
        $productosAfectados = [];

        foreach ($filas as $num => $fila) {
            try {
                $codigoInterno = trim($fila['A'] ?? '');
                $combinacion   = trim($fila['C'] ?? '');

                if ($codigoInterno === '' || $combinacion === '') {
                    $this->omitidos++;
                    continue;
                }

                $producto = Producto::where('empresa_id', $this->empresaId)
                    ->where('codigo_interno', $codigoInterno)
                    ->first();

                if (! $producto) {
                    $this->errores[] = "Variante fila {$num}: producto '{$codigoInterno}' no encontrado.";
                    $this->omitidos++;
                    continue;
                }

                // Parsear COMBINACION → [{valorNombre, pav}, ...]
                $pares         = array_map('trim', explode('/', $combinacion));
                $combinData    = [];
                $valido        = true;

                foreach ($pares as $par) {
                    $partes = array_map('trim', explode(':', $par, 2));
                    if (count($partes) !== 2 || $partes[0] === '' || $partes[1] === '') {
                        $this->errores[] = "Variante fila {$num}: formato inválido '{$par}'. Esperado → Atributo:Valor";
                        $valido = false;
                        break;
                    }
                    [$atributoNombre, $valorNombre] = $partes;

                    $atributo      = $this->resolverAtributo($atributoNombre);
                    $valor         = $this->resolverValor($valorNombre, $atributo->id);
                    $pa            = $this->resolverProductoAtributo($producto->id, $atributo->id);
                    $pav           = $this->resolverProductoAtributoValor($pa->id, $valor->id);
                    $combinData[]  = ['valorNombre' => $valorNombre, 'pav' => $pav];
                }

                if (! $valido) {
                    $this->omitidos++;
                    continue;
                }

                $pavIds = array_map(fn($d) => $d['pav']->id, $combinData);

                // Parsear PRECIO_EXTRA: solo actualiza los valores explícitamente mencionados.
                // Los PAVs no mencionados conservan su precio_adicional actual
                // (setear M:5 una vez aplica a todas las combinaciones que tengan ese valor).
                $rawExtra   = trim($fila['D'] ?? '');
                $extrasMap  = $this->parsearExtras($rawExtra, $combinData);

                foreach ($extrasMap as $pavId => $extra) {
                    foreach ($combinData as $d) {
                        if ($d['pav']->id === $pavId && (float) $d['pav']->precio_adicional !== (float) $extra) {
                            $d['pav']->update(['precio_adicional' => $extra]);
                            $cacheKey = $d['pav']->producto_atributo_id . ':' . $d['pav']->valor_id;
                            unset($this->prodAtribValoresCache[$cacheKey]);
                        }
                    }
                }

                // precio_final = base + suma de precio_adicional de TODOS los PAVs de esta fila
                $totalExtra = 0.0;
                foreach ($combinData as $d) {
                    $totalExtra += (float) $d['pav']->precio_adicional;
                }
                $precioCosto    = $this->parseDecimal($fila['E'] ?? null);
                $stockInicial   = $this->parseDecimal($fila['F'] ?? null) ?? 0;
                $codigoBarras   = trim($fila['G'] ?? '');
                $codigoVariante = trim($fila['B'] ?? '');
                $precioFinal    = (float) $producto->precio_con_descuento + $totalExtra;

                $variante = $this->buscarVariantePorPavs($producto->id, $pavIds);

                if (! $variante) {
                    $variante = Variante::create([
                        'empresa_id'          => $this->empresaId,
                        'producto_id'         => $producto->id,
                        'codigo'              => $codigoVariante ?: $this->generarCodigoVariante($producto->id),
                        'estado'              => 'activo',
                        'precio_final'        => $precioFinal,
                        'precio_costo'        => $precioCosto ?? (float) $producto->precio_costo,
                        'codigo_barras'       => $codigoBarras ?: null,
                        'factor_presentacion' => 1,
                    ]);
                    foreach ($pavIds as $pavId) {
                        VarianteValor::create([
                            'variante_id'                => $variante->id,
                            'producto_atributo_valors_id'=> $pavId,
                        ]);
                    }
                    $this->variantesCreadas++;
                } else {
                    $update = ['estado' => 'activo', 'precio_final' => $precioFinal];
                    if ($precioCosto !== null)    $update['precio_costo']  = $precioCosto;
                    if ($codigoBarras !== '')     $update['codigo_barras'] = $codigoBarras;
                    if ($codigoVariante !== '')   $update['codigo']        = $codigoVariante;
                    $variante->update($update);
                    $this->variantesActualizadas++;
                }

                // Inventario de la variante
                if ($producto->control_de_stock) {
                    $inv = Inventario::firstOrCreate(
                        ['empresa_id' => $this->empresaId, 'producto_id' => $producto->id, 'variante_id' => $variante->id],
                        ['stock_real' => 0, 'stock_reserva' => 0, 'stock_minimo' => 0, 'estado_almacen' => 'activo', 'estado_inventario' => 'agotado']
                    );

                    if ($stockInicial > 0 && (float) $inv->stock_real == 0) {
                        $inv->update([
                            'stock_real'        => $stockInicial,
                            'stock_reserva'     => $stockInicial,
                            'estado_inventario' => 'disponible',
                        ]);
                        $this->kardex->registrar([
                            'empresa_id'      => $this->empresaId,
                            'user_id'         => auth()->id(),
                            'producto_id'     => $producto->id,
                            'variante_id'     => $variante->id,
                            'producto_nombre' => $producto->nombre . ' (' . $combinacion . ')',
                            'tipo'            => 'entrada',
                            'concepto'        => 'Importación masiva',
                            'cantidad'        => $stockInicial,
                            'cantidad_base'   => $stockInicial,
                            'costo_unitario'  => $precioCosto ?: null,
                            'costo_total'     => $precioCosto > 0 ? round($precioCosto * $stockInicial, 4) : null,
                            'stock_antes'     => 0,
                            'stock_despues'   => $stockInicial,
                            'fecha'           => now(),
                        ]);
                    }
                }

                $productosAfectados[$producto->id] = $producto;

            } catch (\Illuminate\Database\QueryException $e) {
                $this->errores[] = "Variante fila {$num}: " . $this->mensajeDb($e);
                $this->omitidos++;
            } catch (\Throwable $e) {
                $this->errores[] = "Variante fila {$num}: " . $e->getMessage();
                $this->omitidos++;
            }
        }

        // Actualizar tiene_variantes y estados de inventario por producto
        foreach ($productosAfectados as $productoId => $producto) {
            $idsActivas = Variante::where('producto_id', $productoId)
                ->where('estado', 'activo')
                ->pluck('id');

            if ($idsActivas->isNotEmpty()) {
                $producto->update(['tiene_variantes' => true]);

                if (! $producto->comparte_stock) {
                    Inventario::where('empresa_id', $this->empresaId)
                        ->where('producto_id', $productoId)
                        ->whereNull('variante_id')
                        ->update(['estado_almacen' => 'inactivo']);

                    Inventario::where('empresa_id', $this->empresaId)
                        ->where('producto_id', $productoId)
                        ->whereIn('variante_id', $idsActivas)
                        ->update(['estado_almacen' => 'activo']);
                }
            }
        }
    }

    // ── Helpers de atributos / variantes ─────────────────────────────────────

    private function resolverAtributo(string $nombre): Atributo
    {
        $key = mb_strtolower(trim($nombre));
        if (! isset($this->atributosCache[$key])) {
            $atributo = Atributo::where('empresa_id', $this->empresaId)
                ->whereRaw('LOWER(nombre) = ?', [$key])
                ->first();

            if (! $atributo) {
                $atributo = Atributo::create([
                    'empresa_id' => $this->empresaId,
                    'nombre'     => trim($nombre),
                    'tipo'       => 'texto',
                    'estado'     => true,
                ]);
            }

            $this->atributosCache[$key] = $atributo;
        }
        return $this->atributosCache[$key];
    }

    private function resolverValor(string $nombre, int $atributoId): Valor
    {
        $key = $atributoId . ':' . mb_strtolower(trim($nombre));
        if (! isset($this->valoresCache[$key])) {
            $valor = Valor::where('atributo_id', $atributoId)
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower(trim($nombre))])
                ->first();

            if (! $valor) {
                $valor = Valor::create([
                    'atributo_id' => $atributoId,
                    'nombre'      => trim($nombre),
                    'valor'       => trim($nombre),
                    'estado'      => 'activo',
                ]);
            }

            $this->valoresCache[$key] = $valor;
        }
        return $this->valoresCache[$key];
    }

    private function resolverProductoAtributo(int $productoId, int $atributoId): ProductoAtributo
    {
        $key = $productoId . ':' . $atributoId;
        if (! isset($this->prodAtributosCache[$key])) {
            $pa = ProductoAtributo::firstOrCreate(
                ['producto_id' => $productoId, 'atributo_id' => $atributoId],
                ['estado' => 'activo']
            );
            if ($pa->estado !== 'activo') {
                $pa->update(['estado' => 'activo']);
            }
            $this->prodAtributosCache[$key] = $pa;
        }
        return $this->prodAtributosCache[$key];
    }

    private function resolverProductoAtributoValor(int $prodAtributoId, int $valorId): ProductoAtributoValor
    {
        $key = $prodAtributoId . ':' . $valorId;
        if (! isset($this->prodAtribValoresCache[$key])) {
            $pav = ProductoAtributoValor::firstOrCreate(
                ['producto_atributo_id' => $prodAtributoId, 'valor_id' => $valorId],
                ['precio_adicional' => 0, 'estado' => 'activo']
            );
            if ($pav->estado !== 'activo') {
                $pav->update(['estado' => 'activo']);
            }
            $this->prodAtribValoresCache[$key] = $pav;
        }
        return $this->prodAtribValoresCache[$key];
    }

    private function buscarVariantePorPavs(int $productoId, array $pavIds): ?Variante
    {
        $query = Variante::where('producto_id', $productoId);
        foreach ($pavIds as $pavId) {
            $query->whereHas('valores', fn($q) => $q->where('producto_atributo_valors.id', $pavId));
        }
        return $query->has('valores', '=', count($pavIds))->first();
    }

    private function generarCodigoVariante(int $productoId): string
    {
        $base = Variante::where('producto_id', $productoId)->count() + 1;
        return 'PROD_' . $productoId . '_' . str_pad($base, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Parsea PRECIO_EXTRA y devuelve solo los PAVs que se mencionan explícitamente.
     * Los PAVs no mencionados NO se incluyen → el llamador no los toca.
     *
     * Formatos:
     *   ""           → {} (no tocar nada)
     *   "5"          → {primer_pav_id: 5}  (atributo único: número directo)
     *   "M:5"        → {pav_M_id: 5}
     *   "M:5 / Rojo:3" → {pav_M_id: 5, pav_Rojo_id: 3}
     *
     * @param  array  $combinData  [{valorNombre:string, pav:ProductoAtributoValor}, ...]
     * @return array  [pav_id => float]  — solo los mencionados
     */
    private function parsearExtras(string $rawExtra, array $combinData): array
    {
        $raw = trim($rawExtra);
        if ($raw === '') {
            return [];
        }

        $limpio = str_replace(',', '.', $raw);

        // Número puro → aplicar al primer PAV (caso de 1 solo atributo)
        if (is_numeric($limpio)) {
            if (empty($combinData)) return [];
            return [$combinData[0]['pav']->id => (float) $limpio];
        }

        // Formato "Valor:precio / Valor2:precio2"
        $result = [];
        foreach (array_map('trim', explode('/', $raw)) as $par) {
            if (! str_contains($par, ':')) continue;
            [$valorBuscado, $extraRaw] = array_map('trim', explode(':', $par, 2));
            $extra = (float) str_replace(',', '.', $extraRaw);

            foreach ($combinData as $d) {
                if (mb_strtolower($d['valorNombre']) === mb_strtolower($valorBuscado)) {
                    $result[$d['pav']->id] = $extra;
                    break;
                }
            }
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function mensajeDb(\Illuminate\Database\QueryException $e): string
    {
        $code = $e->getCode();
        $msg  = $e->getMessage();

        if ($code == 23000) {
            if (str_contains($msg, 'Duplicate entry')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $msg, $m);
                $val   = $m[1] ?? '?';
                $campo = $m[2] ?? '?';
                return "valor duplicado '{$val}' en campo '{$campo}'.";
            }
            if (str_contains($msg, 'cannot be null')) {
                preg_match("/Column '(.+?)' cannot be null/", $msg, $m);
                $campo = $m[1] ?? '?';
                return "el campo '{$campo}' no puede estar vacío.";
            }
        }

        // Genérico: solo el mensaje corto sin el SQL
        return preg_replace('/\(Connection:.*$/s', '', $msg);
    }
}

<?php

namespace App\Services;

use App\Enums\EstadoGeneral;
use App\Enums\ProductoEtiqueta;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Marca;
use App\Models\Produccion;
use App\Models\Producto;
use App\Models\UnidadesMedida;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;

class ProductoImportService
{
    public function __construct(private readonly KardexService $kardex) {}

    private int $empresaId;

    // ── Resultado de la importación ───────────────────────────────────────────

    private int   $creados    = 0;
    private int   $actualizados = 0;
    private int   $omitidos   = 0;
    private array $errores    = [];

    // ── Catálogos cargados una vez para toda la importación ───────────────────

    private array $categoriasCache    = [];
    private array $marcasCache        = [];
    private array $unidadesCache      = [];
    private array $produccionesCache  = [];

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
        $filas = $this->leerExcel($ruta);

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
                    $this->errores[] = "Fila {$num}: PRECIO_VENTA requerido.";
                    $this->omitidos++;
                    continue;
                }

                $precioCosto    = $this->parseDecimal($fila['D'] ?? null) ?? 0;
                $stockMinimo    = $this->parseDecimal($fila['I'] ?? null) ?? 0;
                $stockInicial   = $this->parseDecimal($fila['J'] ?? null) ?? 0;
                $controlDeStock = $this->parseBooleano($fila['P'] ?? null, true);

                $producto = Producto::create([
                    'empresa_id'            => $empresaId,
                    'codigo_interno'        => $codigoInterno !== '' ? $codigoInterno : $this->generarCodigo($empresaId),
                    'nombre'                => $nombre,
                    'slug'                  => $this->generarSlug($nombre, $empresaId),
                    'precio_venta'          => $precioVenta,
                    'precio_costo'          => $precioCosto,
                    'porcentaje_descuento'  => $this->parseDecimal($fila['E'] ?? null) ?? 0,
                    'precio_con_descuento'  => $this->parseDecimal($fila['F'] ?? null),
                    'unidad_medida_id'      => $this->resolverUnidad($fila['G'] ?? null),
                    'estado'                => $this->resolverEstado($fila['H'] ?? null),
                    'codigo_barras'         => trim($fila['K'] ?? '') ?: null,
                    'categoria_id'          => $this->resolverCategoria($fila['L'] ?? null),
                    'marca_id'              => $this->resolverMarca($fila['M'] ?? null),
                    'produccion_id'         => $this->resolverProduccion($fila['N'] ?? null),
                    'etiqueta'              => $this->resolverEtiqueta($fila['O'] ?? null),
                    'control_de_stock'      => $controlDeStock,
                    'visible_en_carta'      => true,
                    'es_cortesia'           => false,
                    'venta_sin_stock'       => false,
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
            } catch (\Throwable $e) {
                $this->errores[] = "Fila {$num}: " . $e->getMessage();
                $this->omitidos++;
            }
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

                // A=CODIGO_INTERNO, B=NOMBRE, C=PRECIO_VENTA, D=DESCUENTO,
                // E=PRECIO_CON_DESCUENTO (fórmula), F=UNIDAD_MEDIDA, G=ESTADO,
                // H=STOCK_MINIMO, I=CODIGO_BARRAS, J=CATEGORIA, K=MARCA,
                // L=AREA_PRODUCCION, M=ETIQUETA, N=CONTROL_STOCK
                // precio_costo NO se actualiza (costo promedio = solo vía compras/ajustes)
                $this->asignarSiNoVacio($cambios, 'nombre',                      $fila['B'] ?? null);
                $this->asignarDecimalSiNoVacio($cambios, 'precio_venta',         $fila['C'] ?? null);
                $this->asignarDecimalSiNoVacio($cambios, 'porcentaje_descuento', $fila['D'] ?? null);
                $this->asignarDecimalSiNoVacio($cambios, 'precio_con_descuento', $fila['E'] ?? null);
                $this->asignarSiNoVacio($cambios, 'codigo_barras',               $fila['I'] ?? null);

                $unidadId = $this->resolverUnidad($fila['F'] ?? null);
                if ($unidadId !== null) $cambios['unidad_medida_id'] = $unidadId;

                $estado = $this->resolverEstado($fila['G'] ?? null);
                if ($estado !== null) $cambios['estado'] = $estado;

                // stock_minimo va en inventarios
                $stockMinimo = $this->parseDecimal($fila['H'] ?? null);
                if ($stockMinimo !== null) {
                    Inventario::where('empresa_id', $empresaId)
                        ->where('producto_id', $producto->id)
                        ->whereNull('variante_id')
                        ->update(['stock_minimo' => $stockMinimo]);
                }

                $categoriaId = $this->resolverCategoria($fila['J'] ?? null);
                if ($categoriaId !== null) $cambios['categoria_id'] = $categoriaId;

                $marcaId = $this->resolverMarca($fila['K'] ?? null);
                if ($marcaId !== null) $cambios['marca_id'] = $marcaId;

                $produccionId = $this->resolverProduccion($fila['L'] ?? null);
                if ($produccionId !== null) $cambios['produccion_id'] = $produccionId;

                $etiqueta = $this->resolverEtiqueta($fila['M'] ?? null);
                if ($etiqueta !== null) $cambios['etiqueta'] = $etiqueta;

                $rawBool = trim($fila['N'] ?? '');
                if ($rawBool !== '') {
                    $cambios['control_de_stock'] = $this->parseBooleano($rawBool, true);
                }

                // Regenerar slug si cambió el nombre
                if (isset($cambios['nombre'])) {
                    $cambios['slug'] = $this->generarSlug($cambios['nombre'], $empresaId, $producto->id);
                }

                if (! empty($cambios)) {
                    $producto->update($cambios);
                }

                $this->actualizados++;
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
            } catch (\Throwable $e) {
                $this->errores[] = "Fila {$num}: " . $e->getMessage();
                $this->omitidos++;
            }
        }

        return $this->resultado();
    }

    // ── Lector de Excel ───────────────────────────────────────────────────────

    /**
     * Lee el Excel y devuelve filas indexadas por letra de columna.
     * Omite la fila 1 (headers). Empieza desde fila 2.
     */
    private function leerExcel(string $ruta): array
    {
        $spreadsheet = IOFactory::load($ruta);
        $sheet       = $spreadsheet->getActiveSheet();
        $filas       = [];

        foreach ($sheet->getRowIterator(2) as $row) {
            $num  = $row->getRowIndex();
            $data = [];

            foreach ($row->getCellIterator('A', 'Z') as $cell) {
                $col = $cell->getColumn();

                // Usar valor calculado para fórmulas (evita leer la cadena "=IF(...")
                $valor = $cell->isFormula()
                    ? $cell->getCalculatedValue()
                    : $cell->getValue();

                // PhpSpreadsheet puede devolver fechas como OLE float
                if (is_float($valor) && XlDate::isDateTime($cell)) {
                    $valor = XlDate::excelToDateTimeObject($valor)->format('Y-m-d');
                }

                $data[$col] = $valor !== null ? (string) $valor : '';
            }

            // Fila completamente vacía → parar
            $contenido = array_filter(array_values($data), fn($v) => trim($v) !== '');
            if (empty($contenido)) continue;

            $filas[$num] = $data;
        }

        return $filas;
    }

    // ── Inicialización ────────────────────────────────────────────────────────

    private function inicializar(int $empresaId): void
    {
        $this->empresaId         = $empresaId;
        $this->creados           = 0;
        $this->actualizados      = 0;
        $this->omitidos          = 0;
        $this->errores           = [];
        $this->categoriasCache   = [];
        $this->marcasCache       = [];
        $this->unidadesCache     = [];
        $this->produccionesCache = [];
        $this->unidadDefaultId   = null;
    }

    private function resultado(): array
    {
        return [
            'creados'     => $this->creados,
            'actualizados'=> $this->actualizados,
            'omitidos'    => $this->omitidos,
            'errores'     => $this->errores,
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
        $simbolo = trim($valor ?? '');

        // Si no se especificó unidad → usar la unidad por defecto de la empresa
        if ($simbolo === '') return $this->unidadDefault();

        $key = mb_strtolower($simbolo);
        if (! isset($this->unidadesCache[$key])) {
            $unidad = UnidadesMedida::where('empresa_id', $this->empresaId)
                ->where('simbolo', $simbolo)
                ->first();

            // Símbolo no encontrado → fallback a unidad por defecto + advertencia
            if (! $unidad) {
                $this->errores[] = "Advertencia: unidad '{$simbolo}' no existe. Se usó la unidad por defecto.";
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
}

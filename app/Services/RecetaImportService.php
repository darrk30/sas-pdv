<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Receta;
use App\Models\RecetaDetalle;
use App\Models\UnidadesMedida;
use App\Models\Variante;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RecetaImportService
{
    private int   $empresaId;
    private int   $creadas     = 0;
    private int   $actualizadas = 0;
    private int   $omitidas    = 0;
    private array $errores     = [];

    /** Cache para no re-consultar la misma unidad dos veces. */
    private array $unidadCache = [];

    // ── API pública ───────────────────────────────────────────────────────────

    public function importar(string $ruta, int $empresaId): array
    {
        $this->empresaId   = $empresaId;
        $this->creadas     = 0;
        $this->actualizadas = 0;
        $this->omitidas    = 0;
        $this->errores     = [];
        $this->unidadCache = [];

        $grupos = $this->leerYAgrupar($ruta);

        DB::transaction(function () use ($grupos) {
            foreach ($grupos as $grupo) {
                $this->procesarGrupo($grupo);
            }
        });

        return [
            'creadas'     => $this->creadas,
            'actualizadas' => $this->actualizadas,
            'omitidas'    => $this->omitidas,
            'errores'     => $this->errores,
        ];
    }

    // ── Lectura y agrupación ──────────────────────────────────────────────────

    /**
     * Lee el Excel y devuelve grupos:
     * [
     *   ['codigo_producto' => '...', 'nombre' => '...', 'insumos' => [
     *     ['codigo' => '...', 'cantidad' => 1.0, 'unidad' => 'L'],
     *     ...
     *   ]],
     *   ...
     * ]
     */
    private function leerYAgrupar(string $ruta): array
    {
        $reader = IOFactory::createReaderForFile($ruta);
        $reader->setReadDataOnly(true);
        $ws = $reader->load($ruta)->getActiveSheet();

        $maxRow = $ws->getHighestDataRow();

        $grupos        = [];
        $codigoActual  = null;
        $nombreActual  = null;
        $insumoActual  = [];

        for ($r = 2; $r <= $maxRow; $r++) {
            // Columnas: A=codigo_producto B=nombre_receta C=codigo_insumo D=cantidad E=unidad
            $codProd   = trim((string) ($ws->getCell("A{$r}")->getValue() ?? ''));
            $nomReceta = trim((string) ($ws->getCell("B{$r}")->getValue() ?? ''));
            $codInsumo = trim((string) ($ws->getCell("C{$r}")->getValue() ?? ''));
            $cantidad  = (string) ($ws->getCell("D{$r}")->getValue() ?? '');
            $unidad    = trim((string) ($ws->getCell("E{$r}")->getValue() ?? ''));

            // Carry-forward: si A vacío pero ya tenemos un producto activo
            if ($codProd === '' && $codigoActual !== null) {
                $codProd   = $codigoActual;
                $nomReceta = $nombreActual;
            }

            // Si no hay código de producto ni insumo → fin de datos
            if ($codProd === '' && $codInsumo === '') {
                break;
            }

            // Nueva receta: guardar la anterior y abrir nueva
            if ($codProd !== $codigoActual || $nomReceta !== $nombreActual) {
                if ($codigoActual !== null && $insumoActual !== []) {
                    $grupos[] = [
                        'codigo_producto' => $codigoActual,
                        'nombre'          => $nombreActual,
                        'insumos'         => $insumoActual,
                    ];
                }
                $codigoActual = $codProd;
                $nombreActual = $nomReceta;
                $insumoActual = [];
            }

            if ($codInsumo === '') continue;

            $cantFloat = (float) str_replace(',', '.', $cantidad);
            if ($cantFloat <= 0) continue;

            $insumoActual[] = [
                'codigo'   => $codInsumo,
                'cantidad' => $cantFloat,
                'unidad'   => $unidad,
            ];
        }

        // Último grupo pendiente
        if ($codigoActual !== null && $insumoActual !== []) {
            $grupos[] = [
                'codigo_producto' => $codigoActual,
                'nombre'          => $nombreActual,
                'insumos'         => $insumoActual,
            ];
        }

        return $grupos;
    }

    // ── Procesamiento de cada grupo ───────────────────────────────────────────

    private function procesarGrupo(array $grupo): void
    {
        $codigoProd = $grupo['codigo_producto'];
        $nombre     = $grupo['nombre'];

        // Resolver producto o variante objetivo
        [$productoId, $varianteId] = $this->resolverTarget($codigoProd);

        if ($productoId === null && $varianteId === null) {
            $this->omitidas++;
            $this->errores[] = "Producto/variante no encontrado: \"{$codigoProd}\"";
            return;
        }

        // Buscar o crear receta
        $receta = Receta::firstOrNew([
            'empresa_id'  => $this->empresaId,
            'producto_id' => $productoId,
            'variante_id' => $varianteId,
        ]);

        $esNueva = ! $receta->exists;
        $receta->nombre = $nombre ?: ($receta->nombre ?? $codigoProd);
        $receta->save();

        // Reemplazar detalles
        $receta->detalles()->delete();

        $detallesOk = 0;
        foreach ($grupo['insumos'] as $pos => $ins) {
            [$insProdId, $insVarId] = $this->resolverTarget($ins['codigo']);

            if ($insProdId === null && $insVarId === null) {
                $this->errores[] = "Insumo no encontrado: \"{$ins['codigo']}\" (receta \"{$nombre}\")";
                continue;
            }

            $unidadId = $this->resolverUnidad($ins['unidad'], $insProdId, $insVarId);

            RecetaDetalle::create([
                'receta_id'          => $receta->id,
                'insumo_producto_id' => $insProdId,
                'insumo_variante_id' => $insVarId,
                'cantidad'           => $ins['cantidad'],
                'unidad_medida_id'   => $unidadId,
            ]);

            $detallesOk++;
        }

        if ($detallesOk === 0) {
            // Si no pudimos crear ningún insumo, borramos la receta vacía
            $receta->delete();
            $this->omitidas++;
            return;
        }

        $esNueva ? $this->creadas++ : $this->actualizadas++;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Busca primero como variante (codigo), luego como producto (codigo_interno).
     * Devuelve [producto_id, variante_id].
     */
    private function resolverTarget(string $codigo): array
    {
        // Variante por código
        $var = Variante::whereHas('producto', fn ($q) => $q->where('empresa_id', $this->empresaId))
            ->where('codigo', $codigo)
            ->first();

        if ($var) {
            return [null, $var->id];
        }

        // Producto simple por codigo_interno
        $prod = Producto::where('empresa_id', $this->empresaId)
            ->where('codigo_interno', $codigo)
            ->first();

        if ($prod) {
            return [$prod->id, null];
        }

        return [null, null];
    }

    /**
     * Busca unidad por nombre o simbolo (insensible a mayúsculas).
     * Si no encuentra → usa la unidad por defecto del insumo.
     * Si unidad vacía   → ídem.
     */
    private function resolverUnidad(string $siglas, ?int $prodId, ?int $varId): ?int
    {
        if ($siglas !== '') {
            $cacheKey = mb_strtolower($siglas);
            if (isset($this->unidadCache[$cacheKey])) {
                return $this->unidadCache[$cacheKey];
            }

            $u = UnidadesMedida::where('empresa_id', $this->empresaId)
                ->where(fn ($q) => $q
                    ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($siglas)])
                    ->orWhereRaw('LOWER(simbolo) = ?', [mb_strtolower($siglas)])
                )
                ->first();

            if ($u) {
                $this->unidadCache[$cacheKey] = $u->id;
                return $u->id;
            }
        }

        // Fallback: unidad del insumo
        if ($varId) {
            return Variante::with('producto.unidadMedida')->find($varId)?->producto?->unidadMedida?->id;
        }
        if ($prodId) {
            return Producto::with('unidadMedida')->find($prodId)?->unidadMedida?->id;
        }

        return null;
    }
}

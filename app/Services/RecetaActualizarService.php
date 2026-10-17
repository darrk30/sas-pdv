<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Receta;
use App\Models\RecetaDetalle;
use App\Models\UnidadesMedida;
use App\Models\Variante;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Actualiza recetas existentes mediante merge (no borra recetas no mencionadas).
 *
 * Para cada grupo del Excel:
 *  - Si la receta no existe → la crea.
 *  - Si existe → sincroniza insumos:
 *      · Insumo en Excel y en sistema → actualiza cantidad / unidad (si unidad es de la misma dimensión).
 *      · Insumo en Excel pero no en sistema → crea el detalle.
 *      · Insumo en sistema pero no en Excel → elimina el detalle.
 *  - Actualiza nombre_receta si cambió.
 *  - Nunca elimina recetas completas que no estén en el Excel.
 */
class RecetaActualizarService
{
    private int   $empresaId;
    private int   $creadas      = 0;
    private int   $actualizadas = 0;
    private int   $omitidas     = 0;
    private array $errores      = [];

    /** Cache de unidades para no repetir queries. */
    private array $unidadCache = [];

    // ── API pública ───────────────────────────────────────────────────────────

    public function actualizar(string $ruta, int $empresaId): array
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
            'creadas'      => $this->creadas,
            'actualizadas' => $this->actualizadas,
            'omitidas'     => $this->omitidas,
            'errores'      => $this->errores,
        ];
    }

    // ── Lectura ───────────────────────────────────────────────────────────────

    private function leerYAgrupar(string $ruta): array
    {
        $reader = IOFactory::createReaderForFile($ruta);
        $reader->setReadDataOnly(true);
        $ws = $reader->load($ruta)->getActiveSheet();

        $maxRow = $ws->getHighestDataRow();

        $grupos       = [];
        $codActual    = null;
        $nomActual    = null;
        $insumosActual = [];

        for ($r = 2; $r <= $maxRow; $r++) {
            // Columnas: A=codigo_producto B=nombre_receta C=codigo_insumo D=cantidad E=unidad
            $codProd   = trim((string) ($ws->getCell("A{$r}")->getValue() ?? ''));
            $nomReceta = trim((string) ($ws->getCell("B{$r}")->getValue() ?? ''));
            $codInsumo = trim((string) ($ws->getCell("C{$r}")->getValue() ?? ''));
            $cantidad  = (string) ($ws->getCell("D{$r}")->getValue() ?? '');
            $unidad    = trim((string) ($ws->getCell("E{$r}")->getValue() ?? ''));

            // Carry-forward
            if ($codProd === '' && $codActual !== null) {
                $codProd   = $codActual;
                $nomReceta = $nomActual;
            }

            if ($codProd === '' && $codInsumo === '') break;

            if ($codProd !== $codActual || ($nomReceta !== '' && $nomReceta !== $nomActual)) {
                if ($codActual !== null && $insumosActual !== []) {
                    $grupos[] = ['codigo_producto' => $codActual, 'nombre' => $nomActual, 'insumos' => $insumosActual];
                }
                $codActual     = $codProd;
                $nomActual     = $nomReceta ?: $codProd;
                $insumosActual = [];
            }

            if ($codInsumo === '') continue;

            $cantFloat = (float) str_replace(',', '.', $cantidad);
            if ($cantFloat <= 0) continue;

            $insumosActual[] = ['codigo' => $codInsumo, 'cantidad' => $cantFloat, 'unidad' => $unidad];
        }

        if ($codActual !== null && $insumosActual !== []) {
            $grupos[] = ['codigo_producto' => $codActual, 'nombre' => $nomActual, 'insumos' => $insumosActual];
        }

        return $grupos;
    }

    // ── Procesamiento de cada grupo ───────────────────────────────────────────

    private function procesarGrupo(array $grupo): void
    {
        $codigoProd = $grupo['codigo_producto'];
        $nombre     = $grupo['nombre'];

        [$productoId, $varianteId] = $this->resolverTarget($codigoProd);

        if ($productoId === null && $varianteId === null) {
            $this->omitidas++;
            $this->errores[] = "Producto/variante no encontrado: \"{$codigoProd}\"";
            return;
        }

        $receta = Receta::firstOrNew([
            'empresa_id'  => $this->empresaId,
            'producto_id' => $productoId,
            'variante_id' => $varianteId,
        ]);

        $esNueva = ! $receta->exists;

        if ($nombre && $nombre !== $codigoProd) {
            $receta->nombre = $nombre;
        } elseif ($esNueva) {
            $receta->nombre = $nombre ?: $codigoProd;
        }

        $receta->save();

        // ── Indexar detalles actuales por clave insumo ────────────────────────
        // Clave: "p_{id}" o "v_{id}"
        $existentes = $receta->detalles()
            ->with(['insumoProducto', 'insumoVariante', 'unidadMedida'])
            ->get()
            ->keyBy(fn ($d) => $d->insumo_variante_id ? "v_{$d->insumo_variante_id}" : "p_{$d->insumo_producto_id}");

        $vistosEnExcel = [];

        foreach ($grupo['insumos'] as $ins) {
            [$insProdId, $insVarId] = $this->resolverTarget($ins['codigo']);

            if ($insProdId === null && $insVarId === null) {
                $this->errores[] = "Insumo no encontrado: \"{$ins['codigo']}\" (receta \"{$nombre}\")";
                continue;
            }

            $claveInsumo = $insVarId ? "v_{$insVarId}" : "p_{$insProdId}";
            $vistosEnExcel[$claveInsumo] = true;

            $detalle     = $existentes->get($claveInsumo);
            $nuevaUnidad = $this->resolverUnidadConDimension(
                $ins['unidad'],
                $insProdId,
                $insVarId,
                $detalle?->unidad_medida_id
            );

            if ($detalle) {
                // Actualizar si cambió algo
                $cambios = [];
                if (abs((float) $detalle->cantidad - $ins['cantidad']) > 0.00001) {
                    $cambios['cantidad'] = $ins['cantidad'];
                }
                if ($nuevaUnidad !== null && $nuevaUnidad !== $detalle->unidad_medida_id) {
                    $cambios['unidad_medida_id'] = $nuevaUnidad;
                }
                if ($cambios !== []) {
                    $detalle->update($cambios);
                }
            } else {
                RecetaDetalle::create([
                    'receta_id'          => $receta->id,
                    'insumo_producto_id' => $insProdId,
                    'insumo_variante_id' => $insVarId,
                    'cantidad'           => $ins['cantidad'],
                    'unidad_medida_id'   => $nuevaUnidad,
                ]);
            }
        }

        // ── Eliminar insumos que ya no están en el Excel ──────────────────────
        foreach ($existentes as $clave => $detalle) {
            if (! isset($vistosEnExcel[$clave])) {
                $detalle->delete();
            }
        }

        $esNueva ? $this->creadas++ : $this->actualizadas++;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolverTarget(string $codigo): array
    {
        $var = Variante::whereHas('producto', fn ($q) => $q->where('empresa_id', $this->empresaId))
            ->where('codigo', $codigo)
            ->first();

        if ($var) return [null, $var->id];

        $prod = Producto::where('empresa_id', $this->empresaId)
            ->where('codigo_interno', $codigo)
            ->first();

        return $prod ? [$prod->id, null] : [null, null];
    }

    /**
     * Resuelve la unidad teniendo en cuenta la dimensión del insumo.
     *
     * - Si las siglas están vacías → null (se mantiene la unidad actual).
     * - Si las siglas no se encuentran → null + warning.
     * - Si se encuentran pero la dimensión difiere → null + warning.
     * - Si se encuentran y la dimensión coincide → id de la nueva unidad.
     */
    private function resolverUnidadConDimension(
        string $siglas,
        ?int   $prodId,
        ?int   $varId,
        ?int   $unidadActualId
    ): ?int {
        if ($siglas === '') return null;

        $cacheKey = mb_strtolower($siglas);
        if (isset($this->unidadCache[$cacheKey])) {
            $nueva = $this->unidadCache[$cacheKey];
        } else {
            $nueva = UnidadesMedida::where('empresa_id', $this->empresaId)
                ->where(fn ($q) => $q
                    ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($siglas)])
                    ->orWhereRaw('LOWER(simbolo) = ?', [mb_strtolower($siglas)])
                )
                ->first();
            $this->unidadCache[$cacheKey] = $nueva;
        }

        if (! $nueva) {
            $this->errores[] = "Unidad \"{$siglas}\" no encontrada — se mantiene la unidad actual.";
            return null;
        }

        // Validar dimensión: debe coincidir con la del insumo
        $dimInsumo = $this->dimensionDelInsumo($prodId, $varId);

        if ($dimInsumo !== null && $nueva->dimension_id !== $dimInsumo) {
            $this->errores[] = "Unidad \"{$siglas}\" es de dimensión distinta a la del insumo — se mantiene la unidad actual.";
            return null;
        }

        return $nueva->id;
    }

    private function dimensionDelInsumo(?int $prodId, ?int $varId): ?int
    {
        if ($varId) {
            $p = Variante::with('producto.unidadMedida')->find($varId);
            return $p?->producto?->unidadMedida?->dimension_id;
        }
        if ($prodId) {
            $p = Producto::with('unidadMedida')->find($prodId);
            return $p?->unidadMedida?->dimension_id;
        }
        return null;
    }
}

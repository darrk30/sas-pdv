<?php

namespace App\Services;

use App\Models\Ajuste;
use App\Models\AjusteDetalle;
use App\Models\Producto;
use App\Models\UnidadesMedida;
use App\Models\Variante;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AjusteImportService
{
    // Cabeceras conocidas que se saltan
    private const SKIP_CODIGO = ['codigo_interno', 'codigo interno', 'código_interno', 'código interno', 'codigo', 'código', 'cod'];

    /**
     * @return array{ajuste: Ajuste|null, importados: int, omitidos: int, errores: string[], filasConDatos: int}
     */
    public function importar(
        string $ruta,
        int    $empresaId,
        string $tipo,
        string $motivo,
        int    $userId,
    ): array {
        $spreadsheet = IOFactory::load($ruta);
        $sheet       = $spreadsheet->getActiveSheet();
        $maxRow      = $sheet->getHighestDataRow();

        $detallesValidos = [];
        $errores         = [];
        $filasConDatos   = 0;
        $omitidos        = 0;

        for ($row = 1; $row <= $maxRow; $row++) {
            $codigoRaw   = trim((string) $sheet->getCell("A{$row}")->getValue());
            $unidadRaw   = trim((string) $sheet->getCell("B{$row}")->getValue());
            $cantidadRaw = trim((string) $sheet->getCell("C{$row}")->getValue());
            $costoRaw    = trim((string) $sheet->getCell("D{$row}")->getValue());

            // Fila vacía
            if ($codigoRaw === '' && $cantidadRaw === '') {
                continue;
            }

            // Fila de instrucción (texto muy largo en A)
            if (mb_strlen($codigoRaw) > 60) {
                continue;
            }

            // Fila de cabecera
            $norm = strtolower(preg_replace('/[\s*()]+/', '', $codigoRaw));
            if (in_array($norm, self::SKIP_CODIGO, true)) {
                continue;
            }

            $filasConDatos++;

            // ── Validaciones básicas ──────────────────────────────────────────
            if ($codigoRaw === '') {
                $errores[] = "Fila {$row}: CODIGO_INTERNO vacío — omitida.";
                $omitidos++;
                continue;
            }

            if ($cantidadRaw === '') {
                $errores[] = "Fila {$row}: CANTIDAD vacía — omitida.";
                $omitidos++;
                continue;
            }

            $cantidad = (float) str_replace(',', '.', $cantidadRaw);
            if ($cantidad <= 0) {
                $errores[] = "Fila {$row}: CANTIDAD debe ser > 0 — omitida.";
                $omitidos++;
                continue;
            }

            // ── Buscar producto / variante ────────────────────────────────────
            [$productoId, $varianteId, $nombreProducto, $unidadProductoId, $costoPorDefecto]
                = $this->buscarItem($codigoRaw, $empresaId);

            if (! $productoId && ! $varianteId) {
                $errores[] = "Fila {$row}: Código '{$codigoRaw}' no encontrado o sin control de stock — omitida.";
                $omitidos++;
                continue;
            }

            // ── Resolver unidad ───────────────────────────────────────────────
            if ($unidadRaw !== '') {
                $unidad = $this->buscarUnidad($unidadRaw);
                if (! $unidad) {
                    $errores[] = "Fila {$row}: Unidad '{$unidadRaw}' no existe en el sistema — omitida.";
                    $omitidos++;
                    continue;
                }

                $unidadProducto = UnidadesMedida::find($unidadProductoId);
                if (! $unidadProducto || $unidad->dimension_id !== $unidadProducto->dimension_id) {
                    $errores[] = "Fila {$row}: Unidad '{$unidadRaw}' no es compatible con la unidad del producto (distinta dimensión) — omitida.";
                    $omitidos++;
                    continue;
                }

                $unidadId = $unidad->id;
            } else {
                $unidadId = $unidadProductoId;
            }

            if (! $unidadId) {
                $errores[] = "Fila {$row}: No se pudo determinar la unidad del producto '{$codigoRaw}' — omitida.";
                $omitidos++;
                continue;
            }

            // ── Costo ─────────────────────────────────────────────────────────
            $costoUnitario = $costoRaw !== ''
                ? (float) str_replace(',', '.', $costoRaw)
                : (float) ($costoPorDefecto ?? 0);

            if ($costoUnitario < 0) {
                $errores[] = "Fila {$row}: COSTO_UNITARIO no puede ser negativo — omitida.";
                $omitidos++;
                continue;
            }

            $detallesValidos[] = [
                'producto_id'    => $productoId,
                'variante_id'    => $varianteId,
                'nombre_producto'=> $nombreProducto,
                'unidad_id'      => $unidadId,
                'cantidad'       => $cantidad,
                'costo_unitario' => $costoUnitario,
                'costo_total'    => round($cantidad * $costoUnitario, 4),
            ];
        }

        if (empty($detallesValidos)) {
            return compact('errores', 'filasConDatos', 'omitidos')
                + ['ajuste' => null, 'importados' => 0];
        }

        $valorTotal = round(
            array_sum(array_column($detallesValidos, 'costo_total')),
            4
        );

        $ajuste = DB::transaction(function () use ($empresaId, $tipo, $motivo, $userId, $valorTotal, $detallesValidos) {
            $ajuste = Ajuste::create([
                'empresa_id'  => $empresaId,
                'user_id'     => $userId,
                'tipo'        => $tipo,
                'motivo'      => $motivo,
                'valor_total' => $valorTotal,
                'estado'      => 'borrador',
            ]);

            foreach ($detallesValidos as $det) {
                AjusteDetalle::create(array_merge($det, ['ajuste_id' => $ajuste->id]));
            }

            return $ajuste;
        });

        return [
            'ajuste'       => $ajuste,
            'importados'   => count($detallesValidos),
            'omitidos'     => $omitidos,
            'errores'      => $errores,
            'filasConDatos'=> $filasConDatos,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Busca por código primero en variantes, luego en productos simples.
     * Retorna [producto_id, variante_id, nombre, unidad_medida_id, precio_costo]
     */
    private function buscarItem(string $codigo, int $empresaId): array
    {
        $codigoLower = strtolower($codigo);

        // 1. Variante con su propio código
        $variante = Variante::withoutGlobalScopes()
            ->whereRaw('LOWER(codigo) = ?', [$codigoLower])
            ->where('estado', 'activo')
            ->whereHas('producto', fn ($q) => $q
                ->where('empresa_id', $empresaId)
                ->where('control_de_stock', true)
                ->where('estado', 'activo'))
            ->with(['producto.unidadMedida', 'valores.valor'])
            ->first();

        if ($variante) {
            return [
                null,
                $variante->id,
                AjusteDetalle::generarNombre(null, $variante),
                $variante->producto?->unidad_medida_id,
                $variante->precio_costo ?? $variante->producto?->precio_costo,
            ];
        }

        // 2. Producto simple (sin variantes activas)
        $producto = Producto::withoutGlobalScopes()
            ->whereRaw('LOWER(codigo_interno) = ?', [$codigoLower])
            ->where('empresa_id', $empresaId)
            ->where('control_de_stock', true)
            ->where('estado', 'activo')
            ->doesntHave('variantesActivas')
            ->with('unidadMedida')
            ->first();

        if ($producto) {
            return [
                $producto->id,
                null,
                $producto->nombre,
                $producto->unidad_medida_id,
                $producto->precio_costo,
            ];
        }

        return [null, null, null, null, null];
    }

    /**
     * Busca unidad por nombre o símbolo (insensible a mayúsculas).
     */
    private function buscarUnidad(string $texto): ?UnidadesMedida
    {
        $lower = strtolower($texto);

        return UnidadesMedida::withoutGlobalScopes()
            ->where('estado', true)
            ->where(fn ($q) => $q
                ->whereRaw('LOWER(nombre) = ?', [$lower])
                ->orWhereRaw('LOWER(simbolo) = ?', [$lower])
            )
            ->first();
    }
}

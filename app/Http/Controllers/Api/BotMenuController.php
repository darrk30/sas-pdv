<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoGeneral;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\Variante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotMenuController extends Controller
{
    public function menu(Request $request, string $slug): JsonResponse
    {
        $token = $request->header('X-Bot-Token') ?? $request->query('token');

        if (! $token || $token !== config('services.bot_api_token')) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $empresa = Empresa::where('slug', $slug)->firstOrFail();

        // ── Productos activos y visibles en carta ─────────────────────────
        $productos = Producto::where('empresa_id', $empresa->id)
            ->where('estado', EstadoGeneral::Activo)
            ->where('visible_en_carta', true)
            ->with([
                'categoria',
                'inventario',
                'variantesActivas.inventario',
                'variantesActivas.valores.valor',
                'variantesActivas.valores.productoAtributo.atributo',
            ])
            ->orderBy('categoria_id')
            ->orderBy('orden')
            ->get();

        // Agrupar por categoría
        $categorias = [];

        foreach ($productos as $producto) {
            $catNombre = $producto->categoria?->nombre ?? 'Sin categoría';
            $catId     = $producto->categoria_id ?? 0;

            if (! isset($categorias[$catId])) {
                $categorias[$catId] = [
                    'id'        => $catId,
                    'nombre'    => $catNombre,
                    'productos' => [],
                ];
            }

            $variantes = $producto->variantesActivas;

            if ($variantes->isNotEmpty()) {
                // ── Producto con variantes ──────────────────────────────────
                $variantesData = $variantes->map(function ($v) use ($producto) {
                    $label = $v->valores->map(function ($pav) {
                        $attr = $pav->productoAtributo?->atributo?->nombre ?? '';
                        $val  = $pav->valor?->nombre ?? '';
                        return $attr && $val ? "{$attr}: {$val}" : ($val ?: $attr);
                    })->filter()->join(' / ');

                    $disponible = true;
                    if ($producto->control_de_stock && ! $producto->venta_sin_stock) {
                        $disponible = (float) ($v->inventario?->stock_reserva ?? 0) > 0;
                    }

                    return [
                        'id'         => $v->id,
                        'label'      => $label ?: ($v->codigo ?? "Variante #{$v->id}"),
                        'precio'     => round((float) $v->precio_final, 2),
                        'disponible' => $disponible,
                    ];
                })->values()->toArray();

                $hayDisponible = collect($variantesData)->contains('disponible', true);

                $categorias[$catId]['productos'][] = [
                    'id'         => $producto->id,
                    'nombre'     => $producto->nombre,
                    'tipo'       => 'variante',
                    'disponible' => $hayDisponible,
                    'variantes'  => $variantesData,
                ];
            } else {
                // ── Producto simple ─────────────────────────────────────────
                $disponible = true;
                if ($producto->control_de_stock && ! $producto->venta_sin_stock) {
                    $disponible = (float) ($producto->inventario?->stock_reserva ?? 0) > 0;
                }

                $precio = ($producto->porcentaje_descuento > 0 && $producto->precio_con_descuento)
                    ? round((float) $producto->precio_con_descuento, 2)
                    : round((float) $producto->precio_venta, 2);

                $categorias[$catId]['productos'][] = [
                    'id'         => $producto->id,
                    'nombre'     => $producto->nombre,
                    'tipo'       => 'simple',
                    'precio'     => $precio,
                    'disponible' => $disponible,
                ];
            }
        }

        // ── Promociones vigentes ──────────────────────────────────────────
        $promociones = Promocion::where('empresa_id', $empresa->id)
            ->with(['detalles.producto', 'detalles.variante.producto'])
            ->get()
            ->filter(fn($p) => $p->estaVigente())
            ->map(function ($promo) {
                $items = $promo->detalles->map(function ($d) {
                    $nombre = $d->variante
                        ? ($d->variante->producto?->nombre ?? 'Producto')
                        : ($d->producto?->nombre ?? 'Producto');
                    $cant = (float) $d->cantidad;
                    return $cant > 1 ? "{$nombre} x{$cant}" : $nombre;
                })->join(', ');

                $stock      = $promo->stockPredictivo();
                $disponible = $stock === null || $stock > 0;

                return [
                    'id'          => $promo->id,
                    'nombre'      => $promo->nombre,
                    'precio'      => round((float) $promo->precio, 2),
                    'descripcion' => $promo->descripcion ?: $items,
                    'items'       => $items,
                    'disponible'  => $disponible,
                ];
            })
            ->values()
            ->toArray();

        return response()->json([
            'empresa'      => $empresa->name,
            'bot_contexto' => $empresa->bot_contexto ?: null,
            'categorias'   => array_values($categorias),
            'promociones'  => $promociones,
        ]);
    }

    // ── GET /api/bot/buscar/{slug}?q=texto ───────────────────────────────────

    public function buscar(Request $request, string $slug): JsonResponse
    {
        $token = $request->header('X-Bot-Token') ?? $request->query('token');

        if (! $token || $token !== config('services.bot_api_token')) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $q = trim((string) $request->query('q', ''));

        if (strlen($q) < 2) {
            return response()->json(['error' => 'El término de búsqueda debe tener al menos 2 caracteres.'], 422);
        }

        $empresa = Empresa::where('slug', $slug)->firstOrFail();

        // ── 1. Buscar variante por su propio código ───────────────────────────
        $variante = Variante::where('empresa_id', $empresa->id)
            ->where('codigo', $q)
            ->whereIn('estado', ['activo', 'inactivo'])
            ->whereHas('producto', fn($pq) => $pq
                ->where('visible_en_carta', true)
                ->whereIn('estado', [EstadoGeneral::Activo, EstadoGeneral::Inactivo])
            )
            ->with([
                'producto',
                'inventario',
                'valores.valor',
                'valores.productoAtributo.atributo',
            ])
            ->first();

        if ($variante) {
            return response()->json($this->_formatVariante($variante));
        }

        // ── 2. Buscar producto por código o nombre ────────────────────────────
        $producto = Producto::where('empresa_id', $empresa->id)
            ->where('visible_en_carta', true)
            ->whereIn('estado', [EstadoGeneral::Activo, EstadoGeneral::Inactivo])
            ->where(function ($pq) use ($q) {
                $pq->where('codigo_interno', $q)
                   ->orWhere('codigo_barras', $q)
                   ->orWhere('nombre', 'like', "%{$q}%");
            })
            ->with([
                'inventario',
                'variantesActivas.inventario',
                'variantesActivas.valores.valor',
                'variantesActivas.valores.productoAtributo.atributo',
            ])
            ->first();

        if (! $producto) {
            return response()->json(['encontrado' => false, 'mensaje' => 'No se encontró ningún producto con ese nombre o código.']);
        }

        // ── Producto con variantes ────────────────────────────────────────────
        if ($producto->variantesActivas->isNotEmpty()) {
            $variantesData = $producto->variantesActivas->map(fn($v) => $this->_formatVarianteItem($v, $producto));

            return response()->json([
                'encontrado'  => true,
                'tipo'        => 'variante',
                'id'          => $producto->id,
                'nombre'      => $producto->nombre,
                'codigo'      => $producto->codigo_interno ?: $producto->codigo_barras,
                'disponible'  => $variantesData->contains('disponible', true),
                'variantes'   => $variantesData->values(),
            ]);
        }

        // ── Producto simple ───────────────────────────────────────────────────
        $disponible = true;
        $stock      = null;

        if ($producto->control_de_stock) {
            $stock      = (float) ($producto->inventario?->stock_reserva ?? 0);
            $disponible = $producto->venta_sin_stock || $stock > 0;
        }

        $precio = ($producto->porcentaje_descuento > 0 && $producto->precio_con_descuento)
            ? round((float) $producto->precio_con_descuento, 2)
            : round((float) $producto->precio_venta, 2);

        return response()->json([
            'encontrado' => true,
            'tipo'       => 'simple',
            'id'         => $producto->id,
            'nombre'     => $producto->nombre,
            'codigo'     => $producto->codigo_interno ?: $producto->codigo_barras,
            'precio'     => $precio,
            'disponible' => $disponible,
            'stock'      => $stock,
        ]);
    }

    private function _formatVariante(Variante $v): array
    {
        $producto   = $v->producto;
        $disponible = true;
        $stock      = null;

        if ($producto?->control_de_stock) {
            $stock      = (float) ($v->inventario?->stock_reserva ?? 0);
            $disponible = $producto->venta_sin_stock || $stock > 0;
        }

        $label = $v->valores->map(function ($pav) {
            $attr = $pav->productoAtributo?->atributo?->nombre ?? '';
            $val  = $pav->valor?->nombre ?? '';
            return $attr && $val ? "{$attr}: {$val}" : ($val ?: $attr);
        })->filter()->join(' / ');

        return [
            'encontrado'    => true,
            'tipo'          => 'variante_directa',
            'variante_id'   => $v->id,
            'producto_id'   => $producto?->id,
            'nombre'        => $producto?->nombre,
            'variante_label'=> $label ?: ($v->codigo ?? "Variante #{$v->id}"),
            'codigo'        => $v->codigo,
            'precio'        => round((float) $v->precio_final, 2),
            'disponible'    => $disponible,
            'stock'         => $stock,
        ];
    }

    private function _formatVarianteItem(Variante $v, Producto $producto): array
    {
        $disponible = true;
        $stock      = null;

        if ($producto->control_de_stock) {
            $stock      = (float) ($v->inventario?->stock_reserva ?? 0);
            $disponible = $producto->venta_sin_stock || $stock > 0;
        }

        $label = $v->valores->map(function ($pav) {
            $attr = $pav->productoAtributo?->atributo?->nombre ?? '';
            $val  = $pav->valor?->nombre ?? '';
            return $attr && $val ? "{$attr}: {$val}" : ($val ?: $attr);
        })->filter()->join(' / ');

        return [
            'variante_id' => $v->id,
            'label'       => $label ?: ($v->codigo ?? "Variante #{$v->id}"),
            'codigo'      => $v->codigo,
            'precio'      => round((float) $v->precio_final, 2),
            'disponible'  => $disponible,
            'stock'       => $stock,
        ];
    }
}

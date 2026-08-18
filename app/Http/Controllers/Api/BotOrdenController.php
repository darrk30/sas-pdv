<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoGeneral;
use App\Enums\TipoDocumento;
use App\Enums\TipoItem;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Inventario;
use App\Models\MetodoEnvio;
use App\Models\MetodoPago;
use App\Models\Orden;
use App\Models\OrdenDetalle;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\Variante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BotOrdenController extends Controller
{
    // ── Middleware de token compartido ────────────────────────────────────────

    private function autenticar(Request $request): bool
    {
        $token = $request->header('X-Bot-Token') ?? $request->query('token');
        return $token && $token === config('services.bot_api_token');
    }

    // ── GET /api/bot/opciones/{slug} ──────────────────────────────────────────
    // Devuelve métodos de envío y pago disponibles para el bot.

    public function opciones(Request $request, string $slug): JsonResponse
    {
        if (! $this->autenticar($request)) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $empresa = Empresa::where('slug', $slug)->firstOrFail();

        $envios = MetodoEnvio::where('empresa_id', $empresa->id)
            ->where('estado', 'activo')
            ->orderBy('costo')
            ->get(['id', 'nombre', 'descripcion', 'costo', 'con_direccion']);

        $pagos = MetodoPago::where('empresa_id', $empresa->id)
            ->where('estado', EstadoGeneral::Activo)
            ->whereIn('visible_en', ['web', 'ambos'])
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'descripcion']);

        return response()->json([
            'metodos_envio' => $envios->map(fn($e) => [
                'id'             => $e->id,
                'nombre'         => $e->nombre,
                'descripcion'    => $e->descripcion,
                'costo'          => (float) $e->costo,
                'con_direccion'  => (bool) $e->con_direccion,
            ]),
            'metodos_pago' => $pagos->map(fn($p) => [
                'id'          => $p->id,
                'nombre'      => $p->nombre,
                'descripcion' => $p->descripcion,
            ]),
        ]);
    }

    // ── POST /api/bot/orden/{slug} ────────────────────────────────────────────

    public function registrar(Request $request, string $slug): JsonResponse
    {
        if (! $this->autenticar($request)) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $empresa = Empresa::where('slug', $slug)->firstOrFail();

        // ── Validación básica ─────────────────────────────────────────────────
        $v = Validator::make($request->all(), [
            'cliente_nombre'    => 'required|string|min:2|max:100',
            'cliente_apellidos' => 'nullable|string|max:100',
            'cliente_num_doc'   => 'required|string|size:8|regex:/^\d{8}$/',
            'cliente_telefono'  => 'required|string|min:7|max:15',
            'metodo_envio_id'   => 'required|integer',
            'metodo_pago_id'    => 'required|integer',
            'direccion'         => 'nullable|string|max:255',
            'departamento'      => 'nullable|string|max:100',
            'provincia'         => 'nullable|string|max:100',
            'distrito'          => 'nullable|string|max:100',
            'notas'             => 'nullable|string|max:500',
            'items'             => 'required|array|min:1',
            'items.*.producto_id'  => 'nullable|integer',
            'items.*.variante_id'  => 'nullable|integer',
            'items.*.promocion_id' => 'nullable|integer',
            'items.*.cantidad'     => 'required|numeric|min:1',
        ], [
            'cliente_nombre.required'   => 'El nombre es obligatorio.',
            'cliente_num_doc.required'  => 'El DNI es obligatorio.',
            'cliente_num_doc.size'      => 'El DNI debe tener exactamente 8 dígitos.',
            'cliente_num_doc.regex'     => 'El DNI solo debe contener números.',
            'cliente_telefono.required' => 'El teléfono es obligatorio.',
            'metodo_envio_id.required'  => 'El método de envío es obligatorio.',
            'metodo_pago_id.required'   => 'El método de pago es obligatorio.',
            'items.required'            => 'El pedido no tiene productos.',
            'items.min'                 => 'El pedido necesita al menos un producto.',
        ]);

        if ($v->fails()) {
            return response()->json(['error' => $v->errors()->first()], 422);
        }

        // ── Validar método de envío y pago pertenecen a la empresa ────────────
        $metodoEnvio = MetodoEnvio::where('id', $request->metodo_envio_id)
            ->where('empresa_id', $empresa->id)
            ->where('estado', 'activo')
            ->first();

        if (! $metodoEnvio) {
            return response()->json(['error' => 'Método de envío no válido.'], 422);
        }

        if ($metodoEnvio->con_direccion && empty($request->direccion)) {
            return response()->json(['error' => 'Este método de envío requiere una dirección.'], 422);
        }

        $metodoPago = MetodoPago::where('id', $request->metodo_pago_id)
            ->where('empresa_id', $empresa->id)
            ->where('estado', EstadoGeneral::Activo)
            ->first();

        if (! $metodoPago) {
            return response()->json(['error' => 'Método de pago no válido.'], 422);
        }

        // ── Resolver y validar items ──────────────────────────────────────────
        $itemsResueltos = [];

        foreach ($request->items as $idx => $raw) {
            $cantidad = (float) $raw['cantidad'];
            $result   = $this->resolverItem($empresa->id, $raw, $cantidad);

            if (isset($result['error'])) {
                return response()->json(['error' => $result['error']], 422);
            }

            $itemsResueltos[] = $result + ['cantidad' => $cantidad];
        }

        if (empty($itemsResueltos)) {
            return response()->json(['error' => 'No hay productos válidos en el pedido.'], 422);
        }

        // ── Calcular totales ──────────────────────────────────────────────────
        $costoEnvio = (float) ($metodoEnvio->costo ?? 0);
        $subtotal   = array_sum(array_column($itemsResueltos, '_subtotal'));
        $total      = $subtotal + $costoEnvio;

        // ── Buscar o crear cliente ────────────────────────────────────────────
        $cliente = Cliente::where('empresa_id', $empresa->id)
            ->where('numero_documento', $request->cliente_num_doc)
            ->first();

        $datosCliente = [
            'nombre'      => $request->cliente_nombre,
            'apellidos'   => $request->cliente_apellidos ?? '',
            'telefono'    => $request->cliente_telefono,
            'direccion'   => $request->direccion ?: null,
            'departamento'=> $request->departamento ?: null,
            'provincia'   => $request->provincia ?: null,
            'distrito'    => $request->distrito ?: null,
        ];

        if ($cliente) {
            $cliente->fill($datosCliente)->save();
        } else {
            $cliente = Cliente::create([
                'empresa_id'       => $empresa->id,
                'tipo_documento'   => TipoDocumento::DNI,
                'numero_documento' => $request->cliente_num_doc,
            ] + $datosCliente);
        }

        // ── Transacción: crear orden + detalles + reservar stock ──────────────
        $igvPct = (float) ($empresa->igv_porcentaje ?? 18);
        $tasaIgv = $igvPct / 100;

        try {
            $orden = DB::transaction(function () use (
                $empresa, $cliente, $metodoEnvio, $metodoPago,
                $itemsResueltos, $costoEnvio, $subtotal, $total,
                $request, $tasaIgv
            ) {
                $geoPartes = array_filter([
                    $request->departamento ?: null,
                    $request->provincia    ?: null,
                    $request->distrito     ?: null,
                ]);

                $clienteNombre = trim($request->cliente_nombre . ' ' . ($request->cliente_apellidos ?? ''));

                $orden = Orden::create([
                    'empresa_id'        => $empresa->id,
                    'cliente_id'        => $cliente->id,
                    'cliente_nombre'    => $clienteNombre,
                    'cliente_tipo_doc'  => 'dni',
                    'cliente_num_doc'   => $request->cliente_num_doc,
                    'cliente_telefono'  => $request->cliente_telefono,
                    'cliente_direccion' => $request->direccion ?: null,
                    'tipo_entrega'      => 'envio',
                    'metodo_envio_id'   => $metodoEnvio->id,
                    'direccion_agencia' => ($metodoEnvio->con_direccion && $request->direccion)
                        ? $request->direccion
                        : null,
                    'costo_envio'       => $costoEnvio,
                    'subtotal'          => $subtotal,
                    'igv'               => 0,
                    'total'             => $total,
                    'metodo_pago_id'    => $metodoPago->id,
                    'notas'             => $request->notas ?: null,
                    'notas_internas'    => $geoPartes ? implode(' / ', $geoPartes) . ' [WhatsApp Bot]' : '[WhatsApp Bot]',
                ]);

                foreach ($itemsResueltos as $item) {
                    $calc = OrdenDetalle::calcular(
                        $item['cantidad'],
                        $item['precio_unitario'],
                        $item['costo_unitario'],
                        0,
                        $tasaIgv
                    );

                    OrdenDetalle::create([
                        'orden_id'        => $orden->id,
                        'tipo_item'       => $item['tipo_item'],
                        'producto_id'     => $item['producto_id'] ?? null,
                        'variante_id'     => $item['variante_id'] ?? null,
                        'promocion_id'    => $item['promocion_id'] ?? null,
                        'descripcion'     => $item['descripcion'],
                        'cantidad'        => $item['cantidad'],
                        'precio_unitario' => $item['precio_unitario'],
                        'valor_unitario'  => $calc['valorUnitario'],
                        'costo_unitario'  => $item['costo_unitario'],
                        'descuento'       => 0,
                        'subtotal'        => $calc['subtotal'],
                        'igv'             => $calc['igv'],
                        'total'           => $calc['total'],
                        'costo_total'     => $calc['costoTotal'],
                    ]);
                }

                $this->reservarStock($empresa->id, $itemsResueltos);

                return $orden;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json([
            'success' => true,
            'codigo'  => $orden->codigo,
            'total'   => (float) $total,
            'mensaje' => "¡Pedido {$orden->codigo} registrado! Total: S/. " . number_format($total, 2),
        ], 201);
    }

    // ── Resolver un ítem del request: valida, obtiene precio y descripción ────

    private function resolverItem(int $empresaId, array $raw, float $cantidad): array
    {
        // ── Promoción ─────────────────────────────────────────────────────────
        if (! empty($raw['promocion_id'])) {
            $promo = Promocion::where('id', $raw['promocion_id'])
                ->where('empresa_id', $empresaId)
                ->with(['detalles.producto.inventario', 'detalles.variante.producto.inventario', 'detalles.variante.inventario'])
                ->first();

            if (! $promo || ! $promo->estaVigente()) {
                return ['error' => "La promoción #{$raw['promocion_id']} no está disponible."];
            }

            $stock = $promo->stockPredictivo();
            if ($stock !== null && $stock < $cantidad) {
                return ['error' => "Sin stock suficiente para la promoción \"{$promo->nombre}\"."];
            }

            $costoUnitario = (float) ($promo->detalles->sum(function ($pd) {
                $costo = $pd->variante?->precio_costo ?? $pd->producto?->precio_costo ?? 0;
                return (float) $costo * (float) $pd->cantidad;
            }));

            return [
                'tipo_item'      => TipoItem::Promocion,
                'promocion_id'   => $promo->id,
                'descripcion'    => $promo->nombre,
                'precio_unitario'=> (float) $promo->precio,
                'costo_unitario' => $costoUnitario,
                '_subtotal'      => (float) $promo->precio * $cantidad,
            ];
        }

        // ── Variante ──────────────────────────────────────────────────────────
        if (! empty($raw['variante_id'])) {
            $variante = Variante::where('id', $raw['variante_id'])
                ->where('empresa_id', $empresaId)
                ->where('estado', 'activo')
                ->with(['producto', 'inventario', 'valores.valor', 'valores.productoAtributo.atributo'])
                ->first();

            if (! $variante || ! $variante->producto) {
                return ['error' => "Variante #{$raw['variante_id']} no encontrada."];
            }

            $producto = $variante->producto;
            if ($producto->estado !== EstadoGeneral::Activo) {
                return ['error' => "El producto \"{$producto->nombre}\" no está disponible."];
            }

            if ($producto->control_de_stock && ! $producto->venta_sin_stock) {
                $stock = (float) ($variante->inventario?->stock_reserva ?? 0);
                if ($stock < $cantidad) {
                    return ['error' => "Sin stock suficiente para \"{$producto->nombre}\"."];
                }
            }

            $varLabel = $variante->valores->map(function ($pav) {
                $attr = $pav->productoAtributo?->atributo?->nombre ?? '';
                $val  = $pav->valor?->nombre ?? '';
                return $attr && $val ? "{$attr}: {$val}" : ($val ?: $attr);
            })->filter()->join(' / ');

            $desc = ($variante->codigo ? $variante->codigo . ' - ' : '')
                . $producto->nombre
                . ($varLabel ? " ({$varLabel})" : '');

            return [
                'tipo_item'      => TipoItem::Variante,
                'producto_id'    => $producto->id,
                'variante_id'    => $variante->id,
                'descripcion'    => $desc,
                'precio_unitario'=> (float) $variante->precio_final,
                'costo_unitario' => (float) ($variante->precio_costo ?? 0),
                '_subtotal'      => (float) $variante->precio_final * $cantidad,
            ];
        }

        // ── Producto simple ───────────────────────────────────────────────────
        if (! empty($raw['producto_id'])) {
            $producto = Producto::where('id', $raw['producto_id'])
                ->where('empresa_id', $empresaId)
                ->where('estado', EstadoGeneral::Activo)
                ->with('inventario')
                ->first();

            if (! $producto) {
                return ['error' => "Producto #{$raw['producto_id']} no encontrado."];
            }

            if ($producto->control_de_stock && ! $producto->venta_sin_stock) {
                $stock = (float) ($producto->inventario?->stock_reserva ?? 0);
                if ($stock < $cantidad) {
                    return ['error' => "Sin stock suficiente para \"{$producto->nombre}\"."];
                }
            }

            $precio = ($producto->porcentaje_descuento > 0 && $producto->precio_con_descuento)
                ? (float) $producto->precio_con_descuento
                : (float) $producto->precio_venta;

            $desc = ($producto->codigo_interno ? $producto->codigo_interno . ' - ' : '')
                . $producto->nombre;

            return [
                'tipo_item'      => TipoItem::Producto,
                'producto_id'    => $producto->id,
                'descripcion'    => $desc,
                'precio_unitario'=> $precio,
                'costo_unitario' => (float) ($producto->precio_costo ?? 0),
                '_subtotal'      => $precio * $cantidad,
            ];
        }

        return ['error' => 'Cada ítem debe tener producto_id, variante_id o promocion_id.'];
    }

    // ── Reservar stock_reserva (igual que Carrito::reservarStockOrden) ────────

    private function reservarStock(int $empresaId, array $items): void
    {
        foreach ($items as $item) {
            $cantidad = (float) $item['cantidad'];

            if (! empty($item['promocion_id'])) {
                $promo = Promocion::with(['detalles.producto', 'detalles.variante.producto'])
                    ->find($item['promocion_id']);

                if (! $promo) continue;

                Promocion::where('id', $promo->id)->increment('usos_actuales', (int) $cantidad);

                foreach ($promo->detalles as $pd) {
                    $cantDet = $cantidad * (float) $pd->cantidad;

                    if ($pd->variante_id) {
                        $prod = $pd->variante?->producto;
                        if (! $prod?->control_de_stock) continue;
                        $inv = Inventario::where('empresa_id', $empresaId)
                            ->where('variante_id', $pd->variante_id)
                            ->lockForUpdate()->first();
                        if (! $prod->venta_sin_stock && (! $inv || (float)$inv->stock_reserva < $cantDet)) {
                            throw new \RuntimeException("Sin stock para \"{$prod->nombre}\".");
                        }
                        if ($inv) $inv->update(['stock_reserva' => (float)$inv->stock_reserva - $cantDet]);

                    } elseif ($pd->producto_id) {
                        $prod = $pd->producto;
                        if (! $prod?->control_de_stock) continue;
                        $inv = Inventario::where('empresa_id', $empresaId)
                            ->where('producto_id', $pd->producto_id)
                            ->whereNull('variante_id')
                            ->lockForUpdate()->first();
                        if (! $prod->venta_sin_stock && (! $inv || (float)$inv->stock_reserva < $cantDet)) {
                            throw new \RuntimeException("Sin stock para \"{$prod->nombre}\".");
                        }
                        if ($inv) $inv->update(['stock_reserva' => (float)$inv->stock_reserva - $cantDet]);
                    }
                }

            } elseif (! empty($item['variante_id'])) {
                $variante = Variante::with('producto')->find($item['variante_id']);
                $prod = $variante?->producto;
                if (! $prod?->control_de_stock) continue;
                $inv = Inventario::where('empresa_id', $empresaId)
                    ->where('variante_id', $item['variante_id'])
                    ->lockForUpdate()->first();
                if (! $prod->venta_sin_stock && (! $inv || (float)$inv->stock_reserva < $cantidad)) {
                    throw new \RuntimeException("Sin stock para \"{$prod->nombre}\".");
                }
                if ($inv) $inv->update(['stock_reserva' => (float)$inv->stock_reserva - $cantidad]);

            } elseif (! empty($item['producto_id'])) {
                $producto = Producto::find($item['producto_id']);
                if (! $producto?->control_de_stock) continue;
                $inv = Inventario::where('empresa_id', $empresaId)
                    ->where('producto_id', $item['producto_id'])
                    ->whereNull('variante_id')
                    ->lockForUpdate()->first();
                if (! $producto->venta_sin_stock && (! $inv || (float)$inv->stock_reserva < $cantidad)) {
                    throw new \RuntimeException("Sin stock para \"{$producto->nombre}\".");
                }
                if ($inv) $inv->update(['stock_reserva' => (float)$inv->stock_reserva - $cantidad]);
            }
        }
    }
}

<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Models\Carrito;
use App\Models\CarritoItem;
use App\Models\ListaDeseo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CarritoController extends Controller
{
    private function carrito(int $empresaId): Carrito
    {
        return Carrito::firstOrCreate([
            'empresa_id' => $empresaId,
            'user_id'    => Auth::guard('cliente')->id(),
        ]);
    }

    public function agregar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'empresa_id'      => 'required|integer',
            'promocion_id'    => 'nullable|integer',
            'producto_id'     => 'nullable|integer',
            'variante_id'     => 'nullable|integer',
            'precio_unitario' => 'required|numeric|min:0',
            'cantidad'        => 'integer|min:1',
        ]);

        $carrito = $this->carrito($data['empresa_id']);
        $item    = $this->buscarItem($carrito, $data);

        if ($item) {
            $item->increment('cantidad', $data['cantidad'] ?? 1);
        } else {
            $carrito->items()->create([
                'promocion_id'    => $data['promocion_id'] ?? null,
                'producto_id'     => $data['producto_id']  ?? null,
                'variante_id'     => $data['variante_id']  ?? null,
                'cantidad'        => $data['cantidad'] ?? 1,
                'precio_unitario' => $data['precio_unitario'],
            ]);
        }

        return response()->json(['ok' => true, 'count' => $carrito->items()->sum('cantidad')]);
    }

    private function buscarItem(Carrito $carrito, array $data): ?CarritoItem
    {
        $q = $carrito->items();

        if (! empty($data['promocion_id'])) {
            return $q->where('promocion_id', $data['promocion_id'])->first();
        }

        $q->whereNull('promocion_id')->where('producto_id', $data['producto_id']);

        if (is_null($data['variante_id'] ?? null)) {
            $q->whereNull('variante_id');
        } else {
            $q->where('variante_id', $data['variante_id']);
        }

        return $q->first();
    }

    public function sincronizar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'empresa_id' => 'required|integer',
            'items'      => 'required|array',
            'items.*.producto_id'     => 'required|integer',
            'items.*.variante_id'     => 'nullable|integer',
            'items.*.precio_unitario' => 'required|numeric|min:0',
            'items.*.cantidad'        => 'required|integer|min:1',
        ]);

        $carrito = $this->carrito($data['empresa_id']);

        foreach ($data['items'] as $item) {
            $existing = $this->buscarItem($carrito, $item);

            if ($existing) {
                if ($item['cantidad'] > $existing->cantidad) {
                    $existing->update(['cantidad' => $item['cantidad']]);
                }
            } else {
                $carrito->items()->create([
                    'promocion_id'    => $item['promocion_id']  ?? null,
                    'producto_id'     => $item['producto_id']   ?? null,
                    'variante_id'     => $item['variante_id']   ?? null,
                    'cantidad'        => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                ]);
            }
        }

        $total = $carrito->items()->sum('cantidad');

        return response()->json(['ok' => true, 'count' => $total]);
    }

    public function toggleDeseo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'empresa_id'  => 'required|integer',
            'producto_id' => 'required|integer',
            'variante_id' => 'nullable|integer',
        ]);

        $userId = Auth::guard('cliente')->id();

        $query = ListaDeseo::where('empresa_id', $data['empresa_id'])
            ->where('user_id', $userId)
            ->where('producto_id', $data['producto_id']);

        if (is_null($data['variante_id'] ?? null)) {
            $query->whereNull('variante_id');
        } else {
            $query->where('variante_id', $data['variante_id']);
        }

        $existe = $query->first();

        if ($existe) {
            $existe->delete();
            $en_deseos = false;
        } else {
            ListaDeseo::create([
                'empresa_id'  => $data['empresa_id'],
                'user_id'     => $userId,
                'producto_id' => $data['producto_id'],
                'variante_id' => $data['variante_id'] ?? null,
            ]);
            $en_deseos = true;
        }

        return response()->json(['ok' => true, 'en_deseos' => $en_deseos]);
    }
}

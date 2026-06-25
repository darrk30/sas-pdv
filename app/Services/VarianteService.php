<?php

namespace App\Services;

use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Variante;
use App\Models\VarianteValor;
use App\Models\ProductoAtributoValor;
use Illuminate\Support\Facades\DB;

class VarianteService
{
    public function syncVariantes(Producto $producto, array $atributosFormulario): void
    {
        $totalValores   = 0;
        $totalAtributos = 0;

        foreach ($atributosFormulario as $item) {
            if (!empty($item['valores_seleccionados'])) {
                $totalAtributos++;
                $totalValores += count($item['valores_seleccionados']);
            }
        }

        if ($totalValores <= $totalAtributos && $totalAtributos > 0) {
            Variante::where('producto_id', $producto->id)->update(['estado' => 'inactivo']);
            Inventario::where('producto_id', $producto->id)
                ->whereNotNull('variante_id')
                ->update(['estado_almacen' => 'inactivo']);
            return;
        }

        DB::transaction(function () use ($producto, $atributosFormulario) {

            Variante::where('producto_id', $producto->id)->update(['estado' => 'inactivo']);
            Inventario::where('producto_id', $producto->id)
                ->whereNotNull('variante_id')
                ->update(['estado_almacen' => 'inactivo']);

            $precioBase = (float) $producto->precio_con_descuento;
            $datos = [];

            foreach ($atributosFormulario as $item) {
                $valores = $item['valores_seleccionados'] ?? [];
                $precios = $item['extra_prices'] ?? [];

                foreach ($valores as $vId) {
                    $pav = ProductoAtributoValor::where('valor_id', $vId)
                        ->whereHas('productoAtributo', fn($q) => $q->where('producto_id', $producto->id))
                        ->where('estado', 'activo')
                        ->first();

                    if ($pav) {
                        $datos[$item['atributo_id']][] = [
                            'pav_id' => $pav->id,
                            'precio' => (float) ($precios[$vId] ?? 0),
                        ];
                    }
                }
            }

            $combinaciones = $this->generarCartesiano($datos);

            // Base para códigos nuevos: total de variantes ya existentes (activas + inactivas).
            // Así nunca colisiona con códigos previos aunque se edite varias veces.
            $baseContador = Variante::where('producto_id', $producto->id)->count();
            $contadorNuevo = 0;

            foreach ($combinaciones as $comb) {
                $pavIds = collect($comb)->pluck('pav_id')->toArray();

                // Busca la variante que tenga EXACTAMENTE estos PAVs.
                // Fix: usar where('id', $pavId) en lugar de whereIn('producto_atributo_valors_id')
                // que referenciaba una columna inexistente en producto_atributo_valors.
                $query = Variante::where('producto_id', $producto->id);

                foreach ($pavIds as $pavId) {
                    $query->whereHas('valores', fn($q) => $q->where('producto_atributo_valors.id', $pavId));
                }

                $varianteExistente = $query->has('valores', '=', count($pavIds))->first();

                if ($varianteExistente) {
                    $variante = $varianteExistente;
                    $variante->update(['estado' => 'activo']);
                } else {
                    $contadorNuevo++;
                    $variante = Variante::create([
                        'empresa_id'   => $producto->empresa_id,
                        'producto_id'  => $producto->id,
                        'codigo'       => 'PROD_' . $producto->id . '_' . str_pad($baseContador + $contadorNuevo, 3, '0', STR_PAD_LEFT),
                        'estado'       => 'activo',
                        'precio_final' => 0,
                    ]);
                }

                $precioFinal = $precioBase;

                foreach ($comb as $data) {
                    $precioFinal += $data['precio'];

                    VarianteValor::updateOrCreate([
                        'variante_id'                 => $variante->id,
                        'producto_atributo_valors_id' => $data['pav_id'],
                    ]);
                }

                $variante->update(['precio_final' => $precioFinal]);
            }
        });
    }

    private function generarCartesiano(array $datos): array
    {
        $resultado = [[]];

        foreach ($datos as $valores) {
            $tmp = [];
            foreach ($resultado as $comb) {
                foreach ($valores as $val) {
                    $tmp[] = array_merge($comb, [$val]);
                }
            }
            $resultado = $tmp;
        }

        return $resultado;
    }
}
<?php

namespace App\Services;

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
            return;
        }

        DB::transaction(function () use ($producto, $atributosFormulario) {

            Variante::where('producto_id', $producto->id)->update(['estado' => 'inactivo']);

            // Siempre usar precio_con_descuento (ya refleja si hay o no descuento)
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

            foreach ($combinaciones as $index => $comb) {
                $pavIds = collect($comb)->pluck('pav_id')->toArray();

                $varianteExistente = Variante::where('producto_id', $producto->id)
                    ->whereHas('valores', function ($q) use ($pavIds) {
                        $q->whereIn('producto_atributo_valors_id', $pavIds);
                    }, '=', count($pavIds))
                    ->first();

                if ($varianteExistente) {
                    $variante = $varianteExistente;
                    $variante->update(['estado' => 'activo']);
                } else {
                    $variante = Variante::create([
                        'empresa_id'   => $producto->empresa_id,
                        'producto_id'  => $producto->id,
                        'codigo'       => 'PROD_' . $producto->id . '_' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
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
<?php

namespace App\Services;

use App\Models\Exclusion;
use App\Models\Producto;
use App\Models\ProductoAtributo;
use App\Models\ProductoAtributoValor;

class ProductoAtributoService
{
    public function sincronizarAtributos(Producto $producto, array $atributosFormulario): void
    {
        foreach ($atributosFormulario as $item) {
            if (empty($item['atributo_id'])) continue;

            $productoAtributo = ProductoAtributo::updateOrCreate(
                ['producto_id' => $producto->id, 'atributo_id' => $item['atributo_id']],
                ['estado' => 'activo']
            );

            $this->sincronizarValores($productoAtributo, $item);
            $this->sincronizarExclusiones($productoAtributo, $item['exclusiones_guardadas'] ?? []);
        }
    }

    private function sincronizarValores(ProductoAtributo $productoAtributo, array $item): void
    {
        $valoresNuevos = $item['valores_seleccionados'] ?? [];
        $extraPrices   = $item['extra_prices'] ?? [];

        $productoAtributo->detallesPrecios()
            ->whereNotIn('valor_id', $valoresNuevos)
            ->update(['estado' => 'inactivo']);

        foreach ($valoresNuevos as $valorId) {
            ProductoAtributoValor::updateOrCreate(
                ['producto_atributo_id' => $productoAtributo->id, 'valor_id' => $valorId],
                ['precio_adicional' => $extraPrices[$valorId] ?? 0, 'estado' => 'activo']
            );
        }
    }

    private function sincronizarExclusiones(ProductoAtributo $productoAtributo, array $exclusiones): void
    {
        $productoAtributo->detallesExclusiones()->delete();

        foreach ($exclusiones as $valorBaseId => $reglas) {
            foreach ($reglas as $regla) {
                if (empty($regla['valor_id'])) continue;

                Exclusion::create([
                    'producto_atributo_id' => $productoAtributo->id,
                    'valor_base_id'        => $valorBaseId,
                    'valor_exluido_id'     => $regla['valor_id'],
                ]);
            }
        }
    }
}
<?php

namespace App\Filament\Pdv\Resources\Productos\Pages;

use App\Filament\Pdv\Resources\Productos\ProductoResource;
use App\Models\Exclusion;
use App\Models\ProductoAtributo;
use App\Models\ProductoAtributoValor;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateProducto extends CreateRecord
{
    protected static string $resource = ProductoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Slug temporal para evitar error de MySQL 1364
        $data['slug'] = Str::slug($data['nombre']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $producto = $this->getRecord();
        $producto->update([
            'slug' => Str::slug($producto->nombre) . '-' . $producto->id,
        ]);

        // 2. REGISTRO DE ATRIBUTOS, VALORES Y EXCLUSIONES
        $estadoFormulario = $this->form->getRawState();
        $atributosFormulario = $estadoFormulario['atributos'] ?? [];

        foreach ($atributosFormulario as $item) {
            if (empty($item['atributo_id'])) continue;

            // Creamos la relación en la tabla puente
            $productoAtributo = ProductoAtributo::create([
                'producto_id' => $producto->id,
                'atributo_id' => $item['atributo_id'],
                'estado'      => 'activo',
            ]);

            $valoresSeleccionados = $item['valores_seleccionados'] ?? [];
            $extraPrices = $item['extra_prices'] ?? [];
            $exclusiones = $item['exclusiones_guardadas'] ?? []; // Obtenemos las exclusiones

            // Registramos cada valor con su precio
            foreach ($valoresSeleccionados as $valorId) {
                ProductoAtributoValor::create([
                    'producto_atributo_id' => $productoAtributo->id,
                    'valor_id'             => $valorId,
                    'precio_adicional'     => $extraPrices[$valorId] ?? 0,
                    'estado'               => 'activo',
                ]);
            }

            // GUARDAMOS LAS EXCLUSIONES
            foreach ($exclusiones as $valorBaseId => $reglasExclusion) {
                foreach ($reglasExclusion as $regla) {
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
}

<?php

namespace App\Filament\Pdv\Resources\Productos\Pages;

use App\Filament\Pdv\Resources\Productos\ProductoResource;
use App\Models\Exclusion;
use App\Models\ProductoAtributo;
use App\Models\ProductoAtributoValor;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditProducto extends EditRecord
{
    protected static string $resource = ProductoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // DeleteAction::make(),
        ];
    }

    // Reconstruimos el slug antes de guardar los cambios
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['slug'] = Str::slug($data['nombre']) . '-' . $this->getRecord()->id;
        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $producto = $this->record->load([
            'atributos' => fn($q) => $q->where('estado', 'activo'),
            'atributos.valores' => fn($q) => $q->where('producto_atributo_valors.estado', 'activo'),
            'atributos.detallesExclusiones.valorExcluido'
        ]);

        $data['atributos'] = $producto->atributos->map(function ($prodAttr) {
            $exclusionesFormateadas = [];
            foreach ($prodAttr->detallesExclusiones as $exclusion) {
                if (!isset($exclusionesFormateadas[$exclusion->valor_base_id])) {
                    $exclusionesFormateadas[$exclusion->valor_base_id] = [];
                }
                $exclusionesFormateadas[$exclusion->valor_base_id][] = [
                    'atributo_id' => $exclusion->valorExcluido->atributo_id ?? null,
                    'valor_id'    => $exclusion->valor_exluido_id,
                ];
            }

            return [
                'atributo_id' => $prodAttr->atributo_id,
                'valores_seleccionados' => $prodAttr->valores->pluck('id')->toArray(),
                'extra_prices' => $prodAttr->valores->mapWithKeys(function ($valor) {
                    return [$valor->id => $valor->pivot->precio_adicional ?? 0];
                })->toArray(),
                'exclusiones_guardadas' => $exclusionesFormateadas,
            ];
        })->toArray();

        return $data;
    }


    // 3. GUARDAR ATRIBUTOS Y VALORES: Se ejecuta después de guardar la tabla 'productos'
    protected function afterSave(): void
    {
        $producto = $this->getRecord();
        $estadoFormulario = $this->form->getState();
        $atributosFormulario = $estadoFormulario['atributos'] ?? [];

        // 1. Identificamos los atributos del formulario
        $idsPresentes = collect($atributosFormulario)->pluck('atributo_id')->filter()->toArray();

        // 2. Obtenemos los IDs (de la tabla puente) de los atributos que se van a DESACTIVAR
        $idsAtributosADesactivar = $producto->atributos()
            ->whereNotIn('atributo_id', $idsPresentes)
            ->pluck('id');

        // 3. Desactivamos todos los VALORES que pertenecen a esos atributos eliminados
        if ($idsAtributosADesactivar->isNotEmpty()) {
            ProductoAtributoValor::whereIn('producto_atributo_id', $idsAtributosADesactivar)->update(['estado' => 'inactivo']);
        }

        // 4. Ahora sí, desactivamos los ATRIBUTOS padres que no están en el nuevo envío
        $producto->atributos()
            ->whereNotIn('atributo_id', $idsPresentes)
            ->update(['estado' => 'inactivo']);

        foreach ($atributosFormulario as $item) {
            if (empty($item['atributo_id'])) continue;

            // Buscamos o creamos
            $productoAtributo = ProductoAtributo::updateOrCreate(
                ['producto_id' => $producto->id, 'atributo_id' => $item['atributo_id']],
                ['estado' => 'activo']
            );

            $valoresNuevos = $item['valores_seleccionados'] ?? [];
            $extraPrices = $item['extra_prices'] ?? [];
            $exclusiones = $item['exclusiones_guardadas'] ?? [];

            // 5. Desactivamos los valores específicos que ya no están seleccionados en este atributo
            $productoAtributo->detallesPrecios()
                ->whereNotIn('valor_id', $valoresNuevos)
                ->update(['estado' => 'inactivo']);

            // 6. Activamos/Actualizamos los valores presentes
            foreach ($valoresNuevos as $valorId) {
                ProductoAtributoValor::updateOrCreate(
                    [
                        'producto_atributo_id' => $productoAtributo->id,
                        'valor_id' => $valorId
                    ],
                    [
                        'precio_adicional' => $extraPrices[$valorId] ?? 0,
                        'estado' => 'activo'
                    ]
                );
            }

            // 7. ACTUALIZAR EXCLUSIONES
            $productoAtributo->detallesExclusiones()->delete();
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

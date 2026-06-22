<?php

namespace App\Filament\Pdv\Resources\Productos\Pages;

use App\Filament\Pdv\Resources\Productos\ProductoResource;
use App\Models\Inventario;
use App\Services\ProductoAtributoService;
use App\Services\VarianteService;
use App\Traits\GestionaVariantes;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateProducto extends CreateRecord
{
    use GestionaVariantes;

    protected static string $resource = ProductoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = Str::slug($data['nombre']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $producto          = $this->getRecord();
        $estadoFormulario  = $this->form->getRawState();
        $stockMinimo       = $estadoFormulario['stock_minimo'] ?? 0;
        $atributosForm     = $estadoFormulario['atributos'] ?? [];

        // Slug definitivo usando el ID real
        $producto->update([
            'slug' => Str::slug($producto->nombre) . '-' . $producto->id,
        ]);

        $esComplejo        = $this->debeGenerarVariantes($atributosForm);
        $estadoBaseInicial = $esComplejo ? 'inactivo' : 'activo';

        // Inventario base (siempre variante_id = null)
        Inventario::create([
            'empresa_id'        => $producto->empresa_id,
            'producto_id'       => $producto->id,
            'variante_id'       => null,
            'stock_real'        => 0,
            'stock_reserva'     => 0,
            'stock_minimo'      => $stockMinimo,
            'estado_almacen'    => $estadoBaseInicial,
            'estado_inventario' => 'agotado',
        ]);

        // Registro de atributos, valores y exclusiones
        app(ProductoAtributoService::class)->sincronizarAtributos($producto, $atributosForm);

        // Variantes e inventarios hijos
        if ($esComplejo) {
            app(VarianteService::class)->syncVariantes($producto, $atributosForm);

            foreach ($producto->variantes()->where('estado', 'activo')->get() as $variante) {
                Inventario::firstOrCreate(
                    [
                        'empresa_id'  => $producto->empresa_id,
                        'producto_id' => $producto->id,
                        'variante_id' => $variante->id,
                    ],
                    [
                        'stock_real'        => 0,
                        'stock_reserva'     => 0,
                        'stock_minimo'      => $stockMinimo,
                        'estado_almacen'    => 'activo',
                        'estado_inventario' => 'agotado',
                    ]
                );
            }
        }

        $tieneVariantes = $producto->variantes()->where('estado', 'activo')->exists();
        $producto->update(['tiene_variantes' => $tieneVariantes]);
    }
}

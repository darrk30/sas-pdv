<?php

namespace App\Filament\Pdv\Resources\Productos\Pages;

use App\Filament\Pdv\Resources\Productos\ProductoResource;
use App\Models\Inventario;
use App\Services\InventarioCoreService;
use App\Services\ProductoAtributoService;
use App\Services\VarianteService;
use App\Traits\GestionaVariantes;
use App\Traits\HasBarcodeScanner;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateProducto extends CreateRecord
{
    use GestionaVariantes, HasBarcodeScanner;

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
        $stockInicial      = (float) ($estadoFormulario['stock_inicial'] ?? 0);
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

        // Stock inicial para productos simples (sin variantes)
        if (! $esComplejo && $stockInicial > 0) {
            if (! $producto->unidad_medida_id) {
                Notification::make()
                    ->title('Stock inicial no aplicado')
                    ->body("El producto \"{$producto->nombre}\" no tiene unidad de medida configurada.")
                    ->warning()
                    ->send();
                return;
            }

            $costoInicial = (float) ($producto->precio_costo ?? 0);
            app(InventarioCoreService::class)->aplicarDetalles(
                empresaId: $producto->empresa_id,
                tipo: 'entrada',
                detalles: collect([[
                    'producto_id'     => $producto->id,
                    'variante_id'     => null,
                    'unidad_id'       => $producto->unidad_medida_id,
                    'cantidad'        => $stockInicial,
                    'costo_unitario'  => $costoInicial,
                    'costo_total'     => round($costoInicial * $stockInicial, 2),
                    'precio_unitario' => (float) $producto->precio_con_descuento,
                    'precio_total'    => round((float) $producto->precio_con_descuento * $stockInicial, 2),
                ]]),
                movible: $producto,
                concepto: 'Stock inicial',
                userId: auth()->id(),
            );
        }
    }
}

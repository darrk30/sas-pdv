<?php

namespace App\Filament\Pdv\Resources\Productos\Pages;

use App\Filament\Pdv\Resources\Productos\ProductoResource;
use App\Models\Inventario;
use App\Models\ProductoAtributo;
use App\Models\ProductoAtributoValor;
use App\Models\Variante;
use App\Services\ProductoAtributoService;
use App\Services\VarianteService;
use App\Traits\GestionaVariantes;
use App\Traits\HasBarcodeScanner;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditProducto extends EditRecord
{
    use GestionaVariantes, HasBarcodeScanner;

    protected static string $resource = ProductoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['slug'] = Str::slug($data['nombre']) . '-' . $this->getRecord()->id;
        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $producto = $this->record->load([
            'atributos'                                    => fn($q) => $q->where('estado', 'activo'),
            'atributos.valores'                            => fn($q) => $q->where('producto_atributo_valors.estado', 'activo'),
            'atributos.detallesExclusiones.valorExcluido',
        ]);

        $data['atributos'] = $producto->atributos->map(function ($prodAttr) {
            $exclusionesFormateadas = [];

            foreach ($prodAttr->detallesExclusiones as $exclusion) {
                $exclusionesFormateadas[$exclusion->valor_base_id][] = [
                    'atributo_id' => $exclusion->valorExcluido->atributo_id ?? null,
                    'valor_id'    => $exclusion->valor_exluido_id,
                ];
            }

            return [
                'atributo_id'           => $prodAttr->atributo_id,
                'valores_seleccionados' => $prodAttr->valores->pluck('id')->toArray(),
                'extra_prices'          => $prodAttr->valores
                    ->mapWithKeys(fn($valor) => [$valor->id => $valor->pivot->precio_adicional ?? 0])
                    ->toArray(),
                'exclusiones_guardadas' => $exclusionesFormateadas,
            ];
        })->toArray();

        return $data;
    }

    protected function afterSave(): void
    {
        $producto         = $this->getRecord();
        $estadoFormulario = $this->form->getRawState();
        $atributosForm    = $estadoFormulario['atributos'] ?? [];

        if (isset($estadoFormulario['stock_minimo'])) {
            Inventario::where('producto_id', $producto->id)
                ->whereNull('variante_id')
                ->update(['stock_minimo' => $estadoFormulario['stock_minimo']]);
        }

        $idsPresentes = collect($atributosForm)->pluck('atributo_id')->filter()->toArray();

        $idsAtributosADesactivar = $producto->atributos()
            ->whereNotIn('atributo_id', $idsPresentes)
            ->pluck('id');

        if ($idsAtributosADesactivar->isNotEmpty()) {
            ProductoAtributoValor::whereIn('producto_atributo_id', $idsAtributosADesactivar)
                ->update(['estado' => 'inactivo']);
        }

        $producto->atributos()
            ->whereNotIn('atributo_id', $idsPresentes)
            ->update(['estado' => 'inactivo']);

        app(ProductoAtributoService::class)->sincronizarAtributos($producto, $atributosForm);

        $esComplejo = $this->debeGenerarVariantes($atributosForm);

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
                        'stock_minimo'      => $estadoFormulario['stock_minimo'] ?? 0,
                        'estado_almacen'    => 'activo',
                        'estado_inventario' => 'agotado',
                    ]
                );
            }

            $this->recalcularPreciosVariantes($producto);

        } else {
            Variante::where('producto_id', $producto->id)
                ->update(['estado' => 'inactivo']);
        }

        $idsVariantesActivas = $producto->variantes()->where('estado', 'activo')->pluck('id');
        $tieneVariantes      = $idsVariantesActivas->isNotEmpty();

        if ($tieneVariantes) {
            // Inventario de producto simple → inactivo
            Inventario::where('producto_id', $producto->id)
                ->whereNull('variante_id')
                ->update(['estado_almacen' => 'inactivo']);

            // Solo las variantes activas (ROJO-L-POLIESTER) → activo
            Inventario::where('producto_id', $producto->id)
                ->whereIn('variante_id', $idsVariantesActivas)
                ->update(['estado_almacen' => 'activo']);

            // Variantes que quedaron inactivas (ROJO-L, AZUL-L) → inactivo en inventario
            Inventario::where('producto_id', $producto->id)
                ->whereNotNull('variante_id')
                ->whereNotIn('variante_id', $idsVariantesActivas)
                ->update(['estado_almacen' => 'inactivo']);
        } else {
            Inventario::where('producto_id', $producto->id)
                ->whereNull('variante_id')
                ->update(['estado_almacen' => 'activo']);

            Inventario::where('producto_id', $producto->id)
                ->whereNotNull('variante_id')
                ->update(['estado_almacen' => 'inactivo']);
        }

        if ($producto->tiene_variantes !== $tieneVariantes) {
            $producto->update(['tiene_variantes' => $tieneVariantes]);
        }
    }

    private function recalcularPreciosVariantes($producto): void
    {
        // Refrescar para leer precio_con_descuento ya persistido en BD
        $producto->refresh();

        // Siempre usar precio_con_descuento (ya refleja si hay o no descuento)
        $precioBase = (float) $producto->precio_con_descuento;

        $variantes = $producto->variantes()
            ->where('estado', 'activo')
            ->with('valores.valor')
            ->get();

        foreach ($variantes as $variante) {
            $extras = $variante->valores->sum(
                fn($v) => (float) ($v->valor?->precio_adicional ?? 0)
            );

            $variante->update(['precio_final' => $precioBase + $extras]);
        }
    }
}
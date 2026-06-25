<?php

namespace App\Filament\Pdv\Resources\Ajustes\Schemas;

use App\Models\AjusteDetalle;
use App\Models\Producto;
use App\Models\UnidadesMedida;
use App\Models\Variante;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AjusteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ── Cabecera ──────────────────────────────────────────────
                Section::make('Información del ajuste')
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('tipo')
                            ->label('Tipo de ajuste')
                            ->options([
                                'entrada' => 'Entrada',
                                'salida'  => 'Salida',
                            ])
                            ->icons([
                                'entrada' => 'heroicon-o-arrow-down-tray',
                                'salida'  => 'heroicon-o-arrow-up-tray',
                            ])
                            ->colors([
                                'entrada' => 'success',
                                'salida'  => 'danger',
                            ])
                            ->inline()
                            ->required()
                            ->columnSpanFull(),

                        Textarea::make('motivo')
                            ->label('Motivo del ajuste')
                            ->placeholder('Ej: Corrección de inventario tras conteo físico...')
                            ->rows(2)
                            ->required()
                            ->columnSpanFull(),
                    ]),

                // ── Detalle de productos ──────────────────────────────────
                Section::make('Productos a ajustar')
                    ->schema([
                        Repeater::make('detalles')
                            ->label('')
                            ->relationship('detalles')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::actualizarTotalGlobal($get, $set);
                            })
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $data['costo_total'] = round((float) ($data['cantidad'] ?? 0) * (float) ($data['costo_unitario'] ?? 0), 4);
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                $data['costo_total'] = round((float) ($data['cantidad'] ?? 0) * (float) ($data['costo_unitario'] ?? 0), 4);
                                return $data;
                            })
                            ->table([
                                TableColumn::make('Producto / Variante'),
                                TableColumn::make('Unidad'),
                                TableColumn::make('Cantidad'),
                                TableColumn::make('Costo Unit.'),
                                TableColumn::make('Subtotal'),
                            ])
                            ->schema([

                                // ── Select unificado: productos simples + variantes ──
                                Select::make('item_id')
                                    ->label('Producto / Variante')
                                    ->placeholder('Buscar producto...')
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->formatStateUsing(function (?Model $record) {
                                        if (! $record) return null;

                                        if ($record->variante_id) {
                                            return 'variante_' . $record->variante_id;
                                        }
                                        if ($record->producto_id) {
                                            return 'producto_' . $record->producto_id;
                                        }
                                        return null;
                                    })
                                    ->getOptionLabelUsing(function ($value): ?string {
                                        if (blank($value)) return null;

                                        [$tipo, $id] = explode('_', $value, 2);

                                        if ($tipo === 'producto') {
                                            return Producto::find($id)?->nombre;
                                        }

                                        $variante = Variante::with(['producto', 'valores.valor'])->find($id);
                                        return $variante ? AjusteDetalle::generarNombre(null, $variante) : null;
                                    })
                                    ->options(function (): array {
                                        $opciones = [];

                                        $simples = Producto::query()
                                            ->doesntHave('variantes')
                                            ->whereHas('inventario')
                                            ->where('control_de_stock', true)
                                            ->where('estado', 'activo')
                                            ->with('unidadMedida')
                                            ->get();

                                        foreach ($simples as $producto) {
                                            $opciones["producto_{$producto->id}"] = $producto->nombre;
                                        }

                                        $variantes = Variante::query()
                                            ->with(['producto', 'valores.valor'])
                                            ->where('estado', 'activo')
                                            ->whereHas('producto', fn($q) => $q
                                                ->where('control_de_stock', true)
                                                ->where('estado', 'activo')
                                            )
                                            ->get();

                                        foreach ($variantes as $variante) {
                                            $nombreVariante = AjusteDetalle::generarNombre(null, $variante);
                                            $opciones["variante_{$variante->id}"] = $nombreVariante;
                                        }

                                        return $opciones;
                                    })
                                    ->afterStateUpdated(function (?string $state, Set $set): void {
                                        if (blank($state)) {
                                            $set('producto_id', null);
                                            $set('variante_id', null);
                                            $set('nombre_producto', null);
                                            $set('unidad_id', null);
                                            $set('costo_unitario', null);
                                            $set('cantidad', null);
                                            $set('costo_total', null);
                                            return;
                                        }

                                        [$tipo, $id] = explode('_', $state, 2);

                                        if ($tipo === 'producto') {
                                            $producto = Producto::with('unidadMedida')->find($id);
                                            $set('producto_id', $producto?->id);
                                            $set('variante_id', null);
                                            $set('nombre_producto', $producto?->nombre);
                                            $set('unidad_id', $producto?->unidad_medida_id);
                                            $set('costo_unitario', $producto?->precio_costo ?? null);
                                        } else {
                                            $variante = Variante::with(['producto.unidadMedida', 'valores.valor'])->find($id);
                                            $set('producto_id', null);
                                            $set('variante_id', $variante?->id);
                                            $set('nombre_producto', AjusteDetalle::generarNombre(null, $variante));
                                            $set('unidad_id', $variante?->producto?->unidad_medida_id);
                                            $set('costo_unitario', $variante?->producto?->precio_costo ?? null);
                                        }

                                        $set('cantidad', null);
                                        $set('costo_total', null);
                                    }),

                                // ── Unidad de medida ──
                                Select::make('unidad_id')
                                    ->label('Unidad')
                                    ->placeholder('Unidad...')
                                    ->required()
                                    ->live()
                                    ->options(function (Get $get): Collection {
                                        $productoId  = $get('producto_id');
                                        $varianteId  = $get('variante_id');
                                        $dimensionId = null;

                                        if ($productoId) {
                                            $dimensionId = Producto::find($productoId)?->unidadMedida?->dimension_id;
                                        } elseif ($varianteId) {
                                            $dimensionId = Variante::find($varianteId)?->producto?->unidadMedida?->dimension_id;
                                        }

                                        if (blank($dimensionId)) {
                                            return collect();
                                        }

                                        return UnidadesMedida::query()
                                            ->where('dimension_id', $dimensionId)
                                            ->where('estado', true)
                                            ->pluck('nombre', 'id');
                                    })
                                    ->getOptionLabelUsing(fn($value) => UnidadesMedida::find($value)?->nombre)
                                    ->disabled(fn(string $operation): bool => $operation === 'edit')
                                    ->dehydrated(true),

                                // ── Cantidad ──
                                TextInput::make('cantidad')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(0.0001)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?float $state, Get $get, Set $set): void {
                                        self::calcularSubtotalItem($get, $set);
                                        self::actualizarTotalGlobal($get, $set, true);
                                    }),

                                // ── Costo unitario ──
                                TextInput::make('costo_unitario')
                                    ->label('Costo unitario')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('S/')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->hint(fn(Get $get): ?string => self::hintCostoDiferente($get))
                                    ->hintColor('warning')
                                    ->hintAction(
                                        Action::make('actualizar_costo_producto')
                                            ->label('Actualizar costo')
                                            ->icon('heroicon-o-arrow-path')
                                            ->requiresConfirmation()
                                            ->modalHeading('¿Actualizar precio de costo?')
                                            ->modalDescription(fn(Get $get): string =>
                                                'Se actualizará el costo registrado del producto a S/ '
                                                . number_format((float) $get('costo_unitario'), 2) . '.'
                                            )
                                            ->modalSubmitActionLabel('Sí, actualizar')
                                            ->visible(fn(Get $get): bool => self::costoEsDiferente($get))
                                            ->action(function (Get $get): void {
                                                $productoId = self::resolverProductoId($get);
                                                if ($productoId) {
                                                    Producto::where('id', $productoId)
                                                        ->update(['precio_costo' => (float) $get('costo_unitario')]);
                                                }
                                            })
                                    )
                                    ->afterStateUpdated(function (?float $state, Get $get, Set $set): void {
                                        self::calcularSubtotalItem($get, $set);
                                        self::actualizarTotalGlobal($get, $set, true);
                                    }),

                                // ── Costo total (Guardado en BD) ──
                                TextInput::make('costo_total')
                                    ->label('Subtotal')
                                    ->prefix('S/')
                                    ->readOnly()
                                    ->numeric(),

                                // ── Campos ocultos ──
                                Hidden::make('producto_id'),
                                Hidden::make('variante_id'),
                                Hidden::make('nombre_producto'),
                            ])
                            ->addActionLabel('Agregar producto')
                            ->reorderable(false)
                            ->defaultItems(1)
                            ->cloneable(),
                    ])->columnSpanFull(),

                // ── Resumen ───────────────────────────────────────────────
                Section::make()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('valor_total')
                                    ->label('Total del ajuste')
                                    ->prefix('S/')
                                    ->readOnly()
                                    ->numeric()
                                    // Dejamos que Filament lo guarde en la BD automáticamente
                                    ->dehydrated(true),
                            ]),
                    ]),
            ]);
    }

    /**
     * Calcula el subtotal (costo_total) de una fila individual del Repeater.
     */
    private static function calcularSubtotalItem(Get $get, Set $set): void
    {
        $cantidad = (float) $get('cantidad');
        $costo    = (float) $get('costo_unitario');

        if ($cantidad > 0 && $costo >= 0) {
            $set('costo_total', round($cantidad * $costo, 4));
        } else {
            $set('costo_total', null);
        }
    }

    private static function resolverProductoId(Get $get): ?int
    {
        $productoId = $get('producto_id');
        if ($productoId) return (int) $productoId;

        $varianteId = $get('variante_id');
        if ($varianteId) {
            return Variante::find($varianteId)?->producto_id;
        }
        return null;
    }

    private static function costoBD(Get $get): ?float
    {
        $productoId = self::resolverProductoId($get);
        if (! $productoId) return null;

        return Producto::find($productoId)?->precio_costo;
    }

    private static function costoEsDiferente(Get $get): bool
    {
        $ingresado = (float) $get('costo_unitario');
        $bd        = self::costoBD($get);

        if ($bd === null || $ingresado <= 0) return false;

        return abs($ingresado - $bd) > 0.001;
    }

    private static function hintCostoDiferente(Get $get): ?string
    {
        if (! self::costoEsDiferente($get)) return null;

        return 'Costo registrado: S/ ' . number_format((float) self::costoBD($get), 2);
    }

    /**
     * Suma todos los subtotales del Repeater y actualiza el valor_total principal.
     */
    private static function actualizarTotalGlobal(Get $get, Set $set, bool $isInsideRepeater = false): void
    {
        // Si estamos dentro del contexto de un item del repeater, necesitamos subir un nivel para obtener todos los detalles
        $detalles = $isInsideRepeater ? $get('../../detalles') : $get('detalles');

        $total = 0;

        if (is_array($detalles)) {
            foreach ($detalles as $item) {
                $cant = (float) ($item['cantidad'] ?? 0);
                $cost = (float) ($item['costo_unitario'] ?? 0);
                $total += ($cant * $cost);
            }
        }

        // Si estamos dentro del repeater, subimos dos niveles para setear el valor padre
        if ($isInsideRepeater) {
            $set('../../valor_total', round($total, 4));
        } else {
            $set('valor_total', round($total, 4));
        }
    }
}

<?php

namespace App\Filament\Pdv\Resources\Compras\Schemas;

use App\Enums\EstadoDespacho;
use App\Enums\EstadoPago;
use App\Enums\TipoComprobante;
use App\Enums\TipoDocumento;
use App\Models\AjusteDetalle;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\UnidadesMedida;
use App\Models\Variante;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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

class CompraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ── Información de la compra ──────────────────────────────
                Section::make('Información de la compra')
                    ->columns(2)
                    ->schema([

                        Select::make('proveedor_id')
                            ->label('Proveedor')
                            ->placeholder('Seleccionar proveedor...')
                            ->searchable()
                            ->nullable()
                            ->relationship('proveedor', 'nombre')
                            ->createOptionForm([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('nombre')
                                            ->label('Nombre / Razón social')
                                            ->required()
                                            ->maxLength(255),

                                        Select::make('tipo_documento')
                                            ->label('Tipo de documento')
                                            ->options(TipoDocumento::class)
                                            ->required(),

                                        TextInput::make('numero_documento')
                                            ->label('Número de documento')
                                            ->required()
                                            ->maxLength(20),

                                        TextInput::make('correo')
                                            ->label('Correo electrónico')
                                            ->email()
                                            ->nullable()
                                            ->maxLength(255),

                                        TextInput::make('telefono')
                                            ->label('Teléfono')
                                            ->tel()
                                            ->nullable()
                                            ->maxLength(20),

                                        TextInput::make('direccion')
                                            ->label('Dirección')
                                            ->nullable()
                                            ->maxLength(255),

                                        TextInput::make('departamento')
                                            ->label('Departamento')
                                            ->nullable()
                                            ->maxLength(100),
                                    ]),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $data['user_id'] = auth()->id();
                                return Proveedor::create($data)->id;
                            }),

                        Select::make('tipo_comprobante')
                            ->label('Tipo de comprobante')
                            ->options(TipoComprobante::class)
                            ->required()
                            ->live(),

                        TextInput::make('serie')
                            ->label('Serie')
                            ->nullable()
                            ->maxLength(10)
                            ->visible(fn(Get $get): bool => $get('tipo_comprobante') !== TipoComprobante::SinComprobante->value),

                        TextInput::make('correlativo')
                            ->label('Correlativo')
                            ->nullable()
                            ->maxLength(20)
                            ->visible(fn(Get $get): bool => $get('tipo_comprobante') !== TipoComprobante::SinComprobante->value),

                        DatePicker::make('fecha_compra')
                            ->label('Fecha de compra')
                            ->required()
                            ->default(today()),

                        ToggleButtons::make('estado_despacho')
                            ->label('Estado de despacho')
                            ->options(EstadoDespacho::class)
                            ->colors([
                                'pendiente' => 'warning',
                                'recibido'  => 'success',
                            ])
                            ->inline()
                            ->required()
                            ->default('pendiente'),

                        ToggleButtons::make('estado_pago')
                            ->label('Estado de pago')
                            ->options(EstadoPago::class)
                            ->colors([
                                'pendiente' => 'warning',
                                'pagado'    => 'success',
                            ])
                            ->inline()
                            ->required()
                            ->live()
                            ->default('pendiente'),

                        Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->nullable()
                            ->rows(2)
                            ->columnSpanFull(),

                        FileUpload::make('archivo_compra')
                            ->label('Archivo de compra')
                            ->nullable()
                            ->directory('compras')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->columnSpanFull(),
                    ]),

                // ── Productos a comprar ───────────────────────────────────
                Section::make('Productos a comprar')
                    ->schema([
                        Repeater::make('detalles')
                            ->label('')
                            ->relationship('detalles')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::recalcularTotales($get, $set, false);
                            })
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $data['costo_total'] = round((float) ($data['cantidad'] ?? 0) * (float) ($data['costo_unitario'] ?? 0), 4);
                                $data['user_id']     = auth()->id();
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                $data['costo_total'] = round((float) ($data['cantidad'] ?? 0) * (float) ($data['costo_unitario'] ?? 0), 4);
                                $data['user_id']     = auth()->id();
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
                                            ->where('estado', '!=', 'archivado')
                                            ->with('unidadMedida')
                                            ->get();

                                        foreach ($simples as $producto) {
                                            $opciones["producto_{$producto->id}"] = $producto->nombre;
                                        }

                                        $variantes = Variante::query()
                                            ->with(['producto', 'valores.valor'])
                                            ->whereHas('producto', fn($q) => $q
                                                ->where('control_de_stock', true)
                                                ->where('estado', '!=', 'archivado')
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
                                    ->getOptionLabelUsing(fn($value) => UnidadesMedida::find($value)?->nombre),

                                // ── Cantidad ──
                                TextInput::make('cantidad')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(0.0001)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?float $state, Get $get, Set $set): void {
                                        self::calcularSubtotalItem($get, $set);
                                        self::recalcularTotales($get, $set, true);
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
                                        self::recalcularTotales($get, $set, true);
                                    }),

                                // ── Costo total (guardado en BD) ──
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

                // ── Pagos ─────────────────────────────────────────────────
                Section::make('Pagos')
                    ->visible(fn(Get $get): bool => $get('estado_pago') === EstadoPago::Pagado->value)
                    ->schema([
                        Repeater::make('pagos')
                            ->label('')
                            ->relationship('pagos')
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                return array_merge($data, ['user_id' => auth()->id()]);
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                return array_merge($data, ['user_id' => auth()->id()]);
                            })
                            ->table([
                                TableColumn::make('Método de Pago'),
                                TableColumn::make('Monto'),
                                TableColumn::make('Referencia'),
                            ])
                            ->schema([

                                Select::make('metodo_pago_id')
                                    ->label('Método de Pago')
                                    ->placeholder('Seleccionar método...')
                                    ->required()
                                    ->live()
                                    ->options(function (): Collection {
                                        return MetodoPago::query()
                                            ->where('estado', 'activo')
                                            ->pluck('nombre', 'id');
                                    }),

                                TextInput::make('monto')
                                    ->label('Monto')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('S/')
                                    ->required(),

                                TextInput::make('referencia')
                                    ->label('Referencia')
                                    ->nullable()
                                    ->maxLength(255)
                                    ->visible(fn(Get $get): bool => (bool) MetodoPago::find($get('metodo_pago_id'))?->requiere_referencia)
                                    ->required(fn(Get $get): bool => (bool) MetodoPago::find($get('metodo_pago_id'))?->requiere_referencia),

                            ])
                            ->addActionLabel('Agregar pago')
                            ->reorderable(false)
                            ->defaultItems(1),
                    ])->columnSpanFull(),

                // ── Totales ───────────────────────────────────────────────
                Section::make('Totales')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('costo_envio')
                                    ->label('Costo de envío')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('S/')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        self::recalcularTotales($get, $set, false);
                                    }),

                                TextInput::make('descuento')
                                    ->label('Descuento')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('S/')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        self::recalcularTotales($get, $set, false);
                                    }),

                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->prefix('S/')
                                    ->readOnly()
                                    ->numeric()
                                    ->default(0),

                                TextInput::make('igv')
                                    ->label('IGV (18%)')
                                    ->prefix('S/')
                                    ->readOnly()
                                    ->numeric()
                                    ->default(0),

                                TextInput::make('total')
                                    ->label('Total')
                                    ->prefix('S/')
                                    ->readOnly()
                                    ->numeric()
                                    ->default(0)
                                    ->extraAttributes(['class' => 'font-bold']),
                            ]),
                    ]),
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────────────────

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

    /**
     * Recalcula subtotal, igv y total en la sección de Totales.
     * $isInsideRepeater = true cuando se llama desde dentro del repeater de detalles.
     */
    private static function recalcularTotales(Get $get, Set $set, bool $isInsideRepeater = false): void
    {
        $detalles = $isInsideRepeater ? $get('../../detalles') : $get('detalles');

        $subtotalDetalles = 0.0;

        if (is_array($detalles)) {
            foreach ($detalles as $item) {
                $cant  = (float) ($item['cantidad'] ?? 0);
                $costo = (float) ($item['costo_unitario'] ?? 0);
                $subtotalDetalles += ($cant * $costo);
            }
        }

        $prefix      = $isInsideRepeater ? '../../' : '';
        $costoEnvio  = (float) ($isInsideRepeater ? $get('../../costo_envio') : $get('costo_envio'));
        $descuento   = (float) ($isInsideRepeater ? $get('../../descuento') : $get('descuento'));

        $base  = $subtotalDetalles + $costoEnvio - $descuento;
        $igv   = round($base * 0.18, 4);
        $total = round($base + $igv, 4);

        $set($prefix . 'subtotal', round($subtotalDetalles, 4));
        $set($prefix . 'igv', $igv);
        $set($prefix . 'total', $total);
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
}

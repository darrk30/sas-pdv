<?php

namespace App\Filament\Pdv\Resources\Productos\Schemas;

use App\Enums\EstadoGeneral;
use App\Enums\ProductoEtiqueta;
use App\Models\Atributo;
use App\Models\ProductoAtributo;
use App\Models\ProductoAtributoValor;
use App\Models\Inventario;
use App\Models\Kardex;
use App\Models\UnidadesMedida;
use App\Models\Variante;
use App\Services\InventarioCoreService;
use App\Models\Valor;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class ProductoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->tabs([
                        // --- PESTAÑA 1: INFORMACIÓN GENERAL ---
                        Tab::make('Información')
                            ->icon('heroicon-m-information-circle')
                            ->schema([
                                FileUpload::make('logo')
                                    ->image()
                                    ->directory('productos')
                                    ->columnSpanFull(),
                                // Fila 1: Nombre (1 columna)
                                TextInput::make('nombre')
                                    ->required()
                                    ->columnSpanFull(),



                                // Fila 3: Precios (Responsivo: 2 en LG/MD, 1 en Default)
                                Grid::make([
                                    'default' => 2,
                                    'md' => 2,
                                    'lg' => 4,
                                ])->schema([
                                    TextInput::make('precio_venta')
                                        ->label('Precio Venta')
                                        ->numeric()
                                        ->prefix('S/')
                                        ->required()
                                        ->default(0) // Valor inicial
                                        ->minValue(0) // Evita negativos
                                        ->dehydrateStateUsing(fn($state) => empty($state) ? 0 : $state) // Si borran todo, envía 0
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                            $precio = floatval($state ?: 0); // Si es null, usa 0
                                            $descuento = floatval($get('porcentaje_descuento') ?: 0);
                                            $precioFinal = $precio - ($precio * ($descuento / 100));
                                            $set('precio_con_descuento', number_format($precioFinal, 2, '.', ''));
                                        }),

                                    TextInput::make('precio_costo')
                                        ->numeric()
                                        ->step(0.01)
                                        ->prefix('S/ ')
                                        ->default(0),

                                    TextInput::make('porcentaje_descuento')
                                        ->label('% Descuento')
                                        ->numeric()
                                        ->suffix('%')
                                        ->default(0)
                                        ->minValue(0)
                                        ->maxValue(100)
                                        ->required()
                                        ->dehydrateStateUsing(fn($state) => empty($state) ? 0 : $state)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                            $descuento = floatval($state ?: 0);
                                            $precio = floatval($get('precio_venta') ?: 0);
                                            $precioFinal = $precio - ($precio * ($descuento / 100));
                                            $set('precio_con_descuento', number_format($precioFinal, 2, '.', ''));
                                        }),

                                    TextInput::make('precio_con_descuento')
                                        ->label('Precio final al cliente')
                                        ->numeric()
                                        ->prefix('S/')
                                        ->readOnly()  // ← reemplaza disabled(); el campo se ve bloqueado pero SÍ envía el valor
                                        ->dehydrated()
                                        ->formatStateUsing(function (Get $get, $state) {
                                            $precio    = floatval($get('precio_venta'));
                                            $descuento = floatval($get('porcentaje_descuento'));
                                            $precioFinal = $precio - ($precio * ($descuento / 100));
                                            return number_format($precioFinal, 2, '.', '');
                                        }),
                                ]),

                                Grid::make([
                                    'default' => 2,
                                    'md' => 2,
                                ])->schema([
                                    // Fila 4: Campo único
                                    Select::make('unidad_medida_id')
                                        ->relationship('unidadMedida', 'nombre')
                                        ->required()
                                        ->preload()
                                        ->native(false)
                                        ->default(function () {
                                            // Busca el ID de la unidad cuyo nombre sea 'Unidad'
                                            return UnidadesMedida::where('nombre', 'Unidad')
                                                ->where('empresa_id', Filament::getTenant()->id)
                                                ->value('id');
                                        }),

                                    Select::make('estado')
                                        ->options(EstadoGeneral::class)
                                        ->default(EstadoGeneral::Activo)
                                        ->required()
                                        ->native(false),
                                ]),

                                Grid::make([
                                    'default' => 1,
                                    'md' => 3,
                                ])->schema([
                                    TextInput::make('stock_minimo')
                                        ->numeric()
                                        ->default(0)
                                        ->dehydrated(false)
                                        ->formatStateUsing(function (?Model $record) {
                                            if (!$record) return 0;
                                            $inventarioGlobal = Inventario::where('producto_id', $record->id)
                                                ->whereNull('variante_id')
                                                ->first();
                                            return $inventarioGlobal ? $inventarioGlobal->stock_minimo : 0;
                                        }),

                                    TextInput::make('stock_total')
                                        ->label('Stock Total')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->default(0)
                                        ->formatStateUsing(fn(?Model $record) => $record ? $record->calcularStockTotal() : 0),

                                    TextInput::make('stock_inicial')
                                        ->label('Stock inicial')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->dehydrated(false)
                                        ->helperText('Solo disponible cuando no hay movimientos previos')
                                        ->visible(fn (?Model $record): bool =>
                                            $record === null ||
                                            (
                                                ! $record->tiene_variantes &&
                                                (float) (Inventario::where('producto_id', $record->id)
                                                    ->whereNull('variante_id')
                                                    ->value('stock_real') ?? 0) <= 0 &&
                                                ! Kardex::where('producto_id', $record->id)
                                                    ->whereNull('variante_id')
                                                    ->exists()
                                            )
                                        ),
                                ]),

                                Grid::make([
                                    'default' => 1,
                                    'md' => 2,
                                    'lg' => 2,
                                ])->schema([
                                    TextInput::make('codigo_interno')
                                        ->label('Código Interno')
                                        ->default(fn() => 'PROD-' . strtoupper(substr(uniqid(), -6)))
                                        ->unique(ignoreRecord: true, modifyRuleUsing: fn($rule) => $rule->where('empresa_id', Filament::getTenant()->id))
                                        ->required(),
                                    TextInput::make('codigo_barras')
                                        ->label('Código de Barras')
                                        ->suffixAction(
                                            Action::make('scan_product_barcode')
                                                ->icon('heroicon-o-camera')
                                                ->label('')
                                                ->color('gray')
                                                ->action(function ($component, $livewire): void {
                                                    $livewire->dispatch('open-barcode-scanner', path: $component->getStatePath());
                                                })
                                        ),

                                ]),

                                RichEditor::make('descripcion')
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'strike',
                                        'link',
                                        'h2',
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Venta')
                            ->icon('heroicon-m-shopping-cart')
                            ->schema([
                                Grid::make(['md' => 2])->schema([
                                    Select::make('categoria_id')
                                        ->relationship('categoria', 'nombre')
                                        ->searchable()
                                        ->preload()
                                        ->createOptionForm([
                                            TextInput::make('nombre')->required(),
                                            FileUpload::make('imagen_url')->directory('categorias'),
                                        ])
                                        ->createOptionUsing(fn(array $data) => \App\Models\Categoria::create($data)->id),

                                    Select::make('marca_id')
                                        ->relationship('marca', 'nombre')
                                        ->searchable()
                                        ->preload()
                                        ->createOptionForm([
                                            TextInput::make('nombre')->required(),
                                            FileUpload::make('logo')->directory('marcas'),
                                        ])
                                        ->createOptionUsing(fn(array $data) => \App\Models\Marca::create($data)->id),
                                ]),

                                Grid::make(['md' => 2])->schema([
                                    Select::make('produccion_id')
                                        ->label('Área de Producción')
                                        ->relationship('produccion', 'nombre')
                                        ->native(false)
                                        ->preload()
                                        ->createOptionForm([
                                            TextInput::make('nombre')->required(),
                                            Select::make('impresora_id')
                                                ->relationship('impresora', 'nombre')
                                                ->createOptionForm([
                                                    TextInput::make('nombre')->required(),
                                                    TextInput::make('descripcion'),
                                                ])
                                                ->createOptionUsing(fn(array $data) => \App\Models\Impresora::create($data)->id),
                                        ])
                                        ->createOptionUsing(fn(array $data) => \App\Models\Produccion::create($data)->id),

                                    Select::make('etiqueta')
                                        ->label('Etiqueta Comercial')
                                        ->options(ProductoEtiqueta::class)
                                        ->native(false),
                                ]),
                            ]),

                        // --- PESTAÑA: VARIANTES ---
                        Tab::make('Variantes y Atributos')
                            ->icon('heroicon-m-list-bullet')
                            ->schema([
                                Repeater::make('atributos')
                                    ->label('Configuración de Variantes')
                                    ->table([
                                        TableColumn::make('Atributos'),
                                        TableColumn::make('Valores'),
                                    ])
                                    ->schema([
                                        Select::make('atributo_id')
                                            ->label('Atributo (Ej: Tamaño, Color)')
                                            ->options(Atributo::where('empresa_id', Filament::getTenant()->id)->pluck('nombre', 'id'))
                                            ->native(false)
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set) {
                                                $set('valores_seleccionados', []);
                                                $set('extra_prices', []);
                                            })
                                            ->createOptionForm([
                                                Grid::make(2)->schema([
                                                    TextInput::make('nombre')->label('Nombre del Atributo')->required(),
                                                    Select::make('tipo')->options(['texto' => 'Texto Normal', 'color' => 'Color Hexadecimal'])->default('texto')->required(),
                                                ])
                                            ])
                                            ->createOptionUsing(fn(array $data) => Atributo::create([
                                                'nombre' => $data['nombre'],
                                                'tipo' => $data['tipo'],
                                                'empresa_id' => Filament::getTenant()->id,
                                            ])->id),

                                        // 2. SELECT MULTIPLE DE VALORES
                                        Select::make('valores_seleccionados')
                                            ->label('Valores')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->options(function (Get $get) {
                                                $attrId = $get('atributo_id');
                                                return $attrId ? Valor::where('atributo_id', $attrId)->pluck('nombre', 'id') : [];
                                            })
                                            ->createOptionForm(function (Get $get) {
                                                $atributo = Atributo::find($get('atributo_id'));
                                                $esColor = $atributo?->tipo === 'color';
                                                return [
                                                    Grid::make(2)->schema([
                                                        TextInput::make('nombre')
                                                            ->label($esColor ? 'Nombre del Color' : 'Nombre de la Opción')
                                                            ->required(),
                                                        ColorPicker::make('valor')
                                                            ->label('Color')
                                                            ->required()
                                                            ->visible($esColor),
                                                    ]),
                                                    Hidden::make('atributo_id')->default($get('atributo_id')),
                                                    Hidden::make('es_color')->default($esColor),
                                                ];
                                            })
                                            ->createOptionUsing(function (array $data) {
                                                if (! ($data['es_color'] ?? false)) $data['valor'] = $data['nombre'];
                                                unset($data['es_color']);
                                                return Valor::create($data)->id;
                                            }),

                                        Hidden::make('extra_prices'),
                                        Hidden::make('exclusiones_guardadas'),
                                    ])
                                    ->extraItemActions([
                                        // ACCIÓN DE PRECIOS
                                        Action::make('configurar_precios')
                                            ->label('Precios Extra')
                                            ->icon('heroicon-m-currency-dollar')
                                            ->color('warning')
                                            ->modalHeading('Precios Adicionales')
                                            ->schema([
                                                Repeater::make('precios_repeater')
                                                    ->hiddenLabel()
                                                    ->addable(false)
                                                    ->deletable(false)
                                                    ->reorderable(false)
                                                    ->schema([
                                                        // Aquí aplicamos el Grid que solicitaste para asegurar las 2 columnas
                                                        Grid::make([
                                                            'default' => 2,
                                                            'md' => 2,
                                                            'lg' => 2,
                                                        ])->schema([
                                                            TextInput::make('name_display')
                                                                ->label('Opción')
                                                                ->disabled()
                                                                ->columnSpan(1),

                                                            Hidden::make('value_id'),

                                                            TextInput::make('extra')
                                                                ->label('Precio Extra (S/)')
                                                                ->numeric()
                                                                ->default(0)
                                                                ->prefix('S/')
                                                                ->required()
                                                                ->columnSpan(1),
                                                        ])
                                                    ])
                                            ])
                                            ->mountUsing(function ($form, $component, $arguments) {
                                                // $component es el Repeater, $arguments['item'] es la clave del ítem
                                                $itemKey  = $arguments['item'] ?? null;
                                                $allItems = $component->getState(); // todo el estado del repeater
                                                $item     = $allItems[$itemKey] ?? [];

                                                $selectedIds  = $item['valores_seleccionados'] ?? [];
                                                $currentPrices = $item['extra_prices'] ?? [];

                                                $valuesData = \App\Models\Valor::whereIn('id', $selectedIds)->get();

                                                $form->fill([
                                                    'precios_repeater' => $valuesData->map(fn($val) => [
                                                        'value_id'     => $val->id,
                                                        'name_display' => $val->nombre,
                                                        'extra'        => $currentPrices[$val->id] ?? 0,
                                                    ])->toArray()
                                                ]);
                                            })
                                            ->action(function ($component, $arguments, array $data) {
                                                $itemKey  = $arguments['item'] ?? null;
                                                $allItems = $component->getState();
                                                $item     = $allItems[$itemKey] ?? [];

                                                $preciosMapeados = collect($data['precios_repeater'])
                                                    ->mapWithKeys(fn($i) => [$i['value_id'] => $i['extra']])
                                                    ->toArray();

                                                // Actualizamos solo ese ítem del repeater
                                                $component->state(
                                                    collect($allItems)->map(function ($it, $key) use ($itemKey, $preciosMapeados) {
                                                        if ($key === $itemKey) {
                                                            $it['extra_prices'] = $preciosMapeados;
                                                        }
                                                        return $it;
                                                    })->toArray()
                                                );
                                            }),

                                        // ACCIÓN DE VALORES Y EXCLUSIONES
                                        Action::make('configurar_valores')
                                            ->label('Valores y Exclusiones')
                                            ->icon('heroicon-m-cog-6-tooth')
                                            ->color('warning')
                                            ->modalHeading('Configurar Valores y sus Exclusiones')
                                            ->modalWidth('3xl')
                                            ->schema([
                                                Repeater::make('lista_valores')
                                                    ->label('')
                                                    ->schema([
                                                        Repeater::make('exclusiones')
                                                            ->label('Excluir este valor de:')
                                                            ->table([
                                                                TableColumn::make('Atributo'),
                                                                TableColumn::make('Valores'),
                                                            ])
                                                            ->schema([
                                                                Select::make('atributo_id')
                                                                    ->label('Atributo')
                                                                    ->native(false)
                                                                    ->options(function (?Model $record) {
                                                                        $productoId = $record->id; // subimos al modal

                                                                        if ($productoId) {
                                                                            $atributos = ProductoAtributo::with('atributo')
                                                                                ->where('producto_id', $productoId)
                                                                                ->where('estado', 'activo')
                                                                                ->get()
                                                                                ->mapWithKeys(fn($item) => [
                                                                                    $item->atributo_id => $item->atributo?->nombre ?? 'Sin nombre'
                                                                                ])
                                                                                ->toArray();

                                                                            if (!empty($atributos)) return $atributos;
                                                                        }

                                                                        return Atributo::where('empresa_id', Filament::getTenant()->id)
                                                                            ->pluck('nombre', 'id')
                                                                            ->toArray();
                                                                    })
                                                                    ->live()
                                                                    ->afterStateUpdated(fn(Set $set) => $set('valor_id', null))
                                                                    ->required()
                                                                    ->columnSpan(1),

                                                                Select::make('valor_id')
                                                                    ->label('Valor a excluir')
                                                                    ->native(false)
                                                                    ->options(function (Get $get, ?Model $record) {
                                                                        $attrIdSeleccionado = $get('atributo_id');
                                                                        $productoId = $record?->id;
                                                                        $valorBaseId = $get('../../id');

                                                                        if (!$attrIdSeleccionado) return [];

                                                                        if ($productoId) {
                                                                            $valores = ProductoAtributoValor::with('valor')
                                                                                ->whereHas('productoAtributo', function ($query) use ($record, $attrIdSeleccionado) {
                                                                                    $query->where('producto_id', $record->id)
                                                                                        ->where('atributo_id', $attrIdSeleccionado)
                                                                                        ->where('estado', 'activo');
                                                                                })
                                                                                ->where('estado', 'activo')
                                                                                ->where('valor_id', '!=', $valorBaseId)
                                                                                ->get()
                                                                                ->mapWithKeys(fn($item) => [
                                                                                    $item->valor_id => $item->valor?->nombre ?? 'Sin nombre'
                                                                                ])
                                                                                ->toArray();

                                                                            if (!empty($valores)) return $valores;
                                                                        }

                                                                        return Valor::where('atributo_id', $attrIdSeleccionado)
                                                                            ->when($valorBaseId, fn($q) => $q->where('id', '!=', $valorBaseId))
                                                                            ->pluck('nombre', 'id')
                                                                            ->toArray();
                                                                    })
                                                                    // ESTO ES LO QUE FALTA: le dice a Filament cómo obtener el label de una opción ya guardada
                                                                    ->getOptionLabelUsing(fn($value) => Valor::find($value)?->nombre ?? $value)
                                                                    ->disableOptionWhen(function (Get $get, $value, $state) {
                                                                        $todasLasExclusiones = $get('../../exclusiones') ?? [];
                                                                        $seleccionados = collect($todasLasExclusiones)->pluck('valor_id')->filter()->toArray();
                                                                        return ($value == $state) ? false : in_array($value, $seleccionados);
                                                                    })
                                                                    ->required()
                                                                    ->columnSpan(1),
                                                            ])
                                                            ->columns(2)
                                                            ->addActionLabel('Añadir Exclusión')
                                                            ->defaultItems(0)
                                                            ->reorderable(false),
                                                    ])
                                                    ->addable(false)
                                                    ->deletable(false)
                                                    ->reorderable(false)
                                                    ->itemLabel(fn(array $state): ?string => $state['nombre'] ?? null),
                                            ])
                                            ->mountUsing(function ($form, $component, $arguments, ?Model $record) {
                                                $itemKey = $arguments['item'] ?? null;
                                                $allItems = $component->getState();
                                                $item = $allItems[$itemKey] ?? [];

                                                $selectedIds = $item['valores_seleccionados'] ?? [];
                                                $exclusionesGuardadas = $item['exclusiones_guardadas'] ?? [];

                                                $valores = Valor::whereIn('id', $selectedIds)->get();

                                                $form->fill([
                                                    '_producto_id' => $record?->id, // <-- inyectamos el ID
                                                    'lista_valores' => $valores->map(fn($v) => [
                                                        'id'          => $v->id,
                                                        'nombre'      => $v->nombre,
                                                        'exclusiones' => $exclusionesGuardadas[$v->id] ?? [],
                                                    ])->toArray(),
                                                ]);
                                            })
                                            ->action(function ($component, $arguments, array $data) {
                                                $itemKey  = $arguments['item'] ?? null;
                                                $allItems = $component->getState();

                                                $exclusionesAGuardar = [];
                                                foreach ($data['lista_valores'] as $valorItem) {
                                                    if (!empty($valorItem['exclusiones'])) {
                                                        $exclusionesAGuardar[$valorItem['id']] = $valorItem['exclusiones'];
                                                    }
                                                }

                                                $component->state(
                                                    collect($allItems)->map(function ($it, $key) use ($itemKey, $exclusionesAGuardar) {
                                                        if ($key === $itemKey) {
                                                            $it['exclusiones_guardadas'] = $exclusionesAGuardar;
                                                        }
                                                        return $it;
                                                    })->toArray()
                                                );
                                            }),
                                    ])
                                    ->addActionLabel('Agregar Atributo al Producto')
                                    ->collapsible()
                                    ->orderColumn(false)
                                    ->columnSpanFull(),
                            ]),

                        // --- PESTAÑA: VARIANTES GENERADAS ---
                        Tab::make('Variantes')
                            ->icon('heroicon-o-squares-2x2')
                            ->visible(fn(?Model $record): bool =>
                                $record?->exists && $record->variantesActivas()->exists()
                            )
                            ->schema([
                                Repeater::make('variantesActivas')
                                    ->relationship('variantesActivas')
                                    ->label('')
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data, Model $record): array {
                                        $stockInicial = (float) ($data['stock_inicial'] ?? 0);

                                        if ($stockInicial > 0 && ! Kardex::where('variante_id', $record->id)->exists()) {
                                            $record->loadMissing('producto');

                                            if (! $record->producto?->unidad_medida_id) {
                                                Notification::make()
                                                    ->title('Stock inicial no aplicado')
                                                    ->body("El producto \"{$record->producto?->nombre}\" no tiene unidad de medida configurada.")
                                                    ->warning()
                                                    ->send();
                                                unset($data['stock_inicial']);
                                                return $data;
                                            }

                                            $costoInicial = (float) ($data['precio_costo'] ?? $record->precio_costo ?? $record->producto?->precio_costo ?? 0);

                                            app(InventarioCoreService::class)->aplicarDetalles(
                                                empresaId: $record->empresa_id,
                                                tipo: 'entrada',
                                                detalles: collect([[
                                                    'producto_id'     => $record->producto_id,
                                                    'variante_id'     => $record->id,
                                                    'unidad_id'       => $record->producto?->unidad_medida_id,
                                                    'cantidad'        => $stockInicial,
                                                    'costo_unitario'  => $costoInicial,
                                                    'costo_total'     => round($costoInicial * $stockInicial, 2),
                                                    'precio_unitario' => (float) $record->precio_final,
                                                    'precio_total'    => round((float) $record->precio_final * $stockInicial, 2),
                                                ]]),
                                                movible: $record->producto,
                                                concepto: 'Stock inicial',
                                                userId: auth()->id(),
                                            );
                                        }

                                        unset($data['stock_inicial']);
                                        return $data;
                                    })
                                    ->table(function (?Model $record): array {
                                        if ($record === null) {
                                            $mostrarStockInicial = true;
                                        } else {
                                            $varianteIds         = $record->variantes()->where('estado', 'activo')->pluck('id');
                                            $idsConKardex        = Kardex::whereIn('variante_id', $varianteIds)->pluck('variante_id');
                                            $mostrarStockInicial = $varianteIds->diff($idsConKardex)->isNotEmpty();
                                        }

                                        return array_values(array_filter([
                                            TableColumn::make('Variante'),
                                            TableColumn::make('Cód. Interno'),
                                            TableColumn::make('Cód. de Barras'),
                                            TableColumn::make('Precio Final'),
                                            TableColumn::make('Costo'),
                                            TableColumn::make('Stock'),
                                            $mostrarStockInicial ? TableColumn::make('Stock inicial') : null,
                                        ]));
                                    })
                                    ->schema([

                                        TextInput::make('_nombre_display')
                                            ->label('Variante')
                                            ->readOnly()
                                            ->dehydrated(false)
                                            ->formatStateUsing(function ($state, ?Model $record): string {
                                                if (! $record) return '—';
                                                return $record->valores
                                                    ->map(fn($pav) => $pav->valor?->nombre)
                                                    ->filter()
                                                    ->implode(' · ') ?: '—';
                                            }),

                                        TextInput::make('codigo')
                                            ->label('Cód. Interno')
                                            ->nullable()
                                            ->maxLength(100),

                                        TextInput::make('codigo_barras')
                                            ->label('Cód. de Barras')
                                            ->nullable()
                                            ->maxLength(100)
                                            ->suffixAction(
                                                Action::make('scan_variante_barcode')
                                                    ->icon('heroicon-o-camera')
                                                    ->label('')
                                                    ->color('gray')
                                                    ->action(function ($component, $livewire): void {
                                                        $livewire->dispatch('open-barcode-scanner', path: $component->getStatePath());
                                                    })
                                            ),

                                        TextInput::make('precio_final')
                                            ->label('Precio Final')
                                            ->prefix('S/')
                                            ->readOnly()
                                            ->dehydrated(false),

                                        TextInput::make('precio_costo')
                                            ->label('Costo')
                                            ->prefix('S/')
                                            ->numeric()
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->nullable(),

                                        TextInput::make('_stock_display')
                                            ->label('Stock')
                                            ->readOnly()
                                            ->dehydrated(false)
                                            ->formatStateUsing(function ($state, ?Model $record): string {
                                                return number_format((float) ($record?->inventario?->stock_real ?? 0), 2);
                                            }),

                                        TextInput::make('stock_inicial')
                                            ->label('Stock inicial')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->visible(fn (?Model $record): bool =>
                                                $record === null ||
                                                (
                                                    (float) ($record->inventario?->stock_real ?? 0) <= 0 &&
                                                    ! Kardex::where('variante_id', $record->id)->exists()
                                                )
                                            ),

                                    ])
                                    ->defaultItems(0),
                            ]),

                        // --- PESTAÑA 3: CONFIGURACIÓN ---
                        Tab::make('Configuración')
                            ->icon('heroicon-m-cog-6-tooth')
                            ->schema([
                                Section::make('Opciones adicionales del producto')
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                        'lg' => 2,
                                    ])
                                    ->schema([
                                        Toggle::make('es_cortesia')
                                            ->label('Es de cortesía'),

                                        Toggle::make('visible_en_carta')
                                            ->label('Visible en carta')
                                            ->default(true),

                                        Toggle::make('control_de_stock')
                                            ->label('Control de stock')
                                            ->default(true),

                                        Toggle::make('venta_sin_stock')
                                            ->label('Venta sin stock'),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}

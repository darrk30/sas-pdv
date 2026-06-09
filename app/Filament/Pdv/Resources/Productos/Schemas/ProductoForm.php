<?php

namespace App\Filament\Pdv\Resources\Productos\Schemas;

use App\Enums\EstadoGeneral;
use App\Enums\ProductoEtiqueta;
use App\Models\Atributo;
use App\Models\ProductoAtributo;
use App\Models\ProductoAtributoValor;
use App\Models\Valor;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
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
                                // Fila 1: Nombre (1 columna)
                                TextInput::make('nombre')
                                    ->required()
                                    ->columnSpanFull(),

                                // Fila 2: Datos principales (Responsivo: 3 en LG, 2 en MD, 1 en Default)
                                Grid::make([
                                    'default' => 1,
                                    'md' => 2,
                                    'lg' => 2,
                                ])->schema([
                                    TextInput::make('codigo_interno')
                                        ->label('Código Interno')
                                        ->required()
                                        ->unique(ignoreRecord: true, modifyRuleUsing: fn($rule) => $rule->where('empresa_id', Filament::getTenant()->id)),

                                    TextInput::make('codigo_barras')
                                        ->label('Código de Barras'),

                                ]),

                                // Fila 3: Precios (Responsivo: 2 en LG/MD, 1 en Default)
                                Grid::make([
                                    'default' => 2,
                                    'md' => 2,
                                ])->schema([
                                    TextInput::make('precio_costo')
                                        ->numeric()
                                        ->prefix('S/ ')
                                        ->default(0),

                                    TextInput::make('precio_venta')
                                        ->numeric()
                                        ->prefix('S/ ')
                                        ->default(0)
                                        ->required(),
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
                                        ->native(false),

                                    Select::make('estado')
                                        ->options(EstadoGeneral::class)
                                        ->default(EstadoGeneral::Activo)
                                        ->required()
                                        ->native(false),
                                ]),
                                // FileUpload y RichEditor (1 columna - Full)
                                FileUpload::make('logo')
                                    ->image()
                                    ->directory('productos')
                                    ->columnSpanFull(),

                                RichEditor::make('descripcion')
                                    ->columnSpanFull(),
                            ]),

                        // --- PESTAÑA 2: VENTA ---
                        Tab::make('Venta')
                            ->icon('heroicon-m-shopping-cart')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('categoria_id')
                                        ->relationship('categoria', 'nombre')
                                        ->searchable()
                                        ->preload(),

                                    Select::make('marca_id')
                                        ->relationship('marca', 'nombre')
                                        ->searchable()
                                        ->preload(),


                                ]),
                                Grid::make(2)->schema([
                                    Select::make('produccion_id')
                                        ->label('Área de Producción')
                                        ->relationship('produccion', 'nombre')
                                        ->native(false)
                                        ->preload(),

                                    Select::make('etiqueta')
                                        ->label('Etiqueta Comercial')
                                        ->options(ProductoEtiqueta::class)
                                        ->native(false),
                                ]),
                            ]),

                        // --- PESTAÑA: VARIANTES ---
                        // --- PESTAÑA: VARIANTES (CORREGIDO) ---
                        Tab::make('Variantes y Atributos')
                            ->icon('heroicon-m-list-bullet')
                            ->schema([
                                // Repeater::make('atributos')
                                //     ->label('Configuración de Variantes')
                                //     ->table([
                                //         TableColumn::make('Atributos'),
                                //         TableColumn::make('Valores'),
                                //     ])
                                //     ->schema([
                                //             // 1. SELECT DE ATRIBUTO
                                //             Select::make('atributo_id')
                                //                 ->label('Atributo (Ej: Tamaño, Color)')
                                //                 ->options(Atributo::where('empresa_id', Filament::getTenant()->id)->pluck('nombre', 'id'))
                                //                 ->required()
                                //                 ->native(false)
                                //                 ->searchable()
                                //                 ->preload()
                                //                 ->live()
                                //                 ->afterStateUpdated(function (Set $set) {
                                //                     $set('valores_seleccionados', []);
                                //                     $set('extra_prices', []);
                                //                 })
                                //                 ->createOptionForm([
                                //                     Grid::make(2)->schema([
                                //                         TextInput::make('nombre')->label('Nombre del Atributo')->required(),
                                //                         Select::make('tipo')->options(['texto' => 'Texto Normal', 'color' => 'Color Hexadecimal'])->default('texto')->required(),
                                //                     ])
                                //                 ])
                                //                 ->createOptionUsing(fn(array $data) => Atributo::create([
                                //                     'nombre' => $data['nombre'],
                                //                     'tipo' => $data['tipo'],
                                //                     'empresa_id' => Filament::getTenant()->id,
                                //                 ])->id),

                                //             // 2. SELECT MULTIPLE DE VALORES
                                //             Select::make('valores_seleccionados')
                                //                 ->label('Valores')
                                //                 ->multiple()
                                //                 ->searchable()
                                //                 ->preload()
                                //                 ->live()
                                //                 ->options(function (Get $get) {
                                //                     $attrId = $get('atributo_id');
                                //                     return $attrId ? Valor::where('atributo_id', $attrId)->pluck('nombre', 'id') : [];
                                //                 })
                                //                 ->createOptionForm(function (Get $get) {
                                //                     $atributo = Atributo::find($get('atributo_id'));
                                //                     $esColor = $atributo?->tipo === 'color';
                                //                     return [
                                //                         TextInput::make('nombre')->label($esColor ? 'Nombre del Color' : 'Nombre de la Opción')->required(),
                                //                         ColorPicker::make('valor')->label('Código Hexadecimal')->required()->visible($esColor),
                                //                         Hidden::make('atributo_id')->default($get('atributo_id')),
                                //                         Hidden::make('es_color')->default($esColor),
                                //                     ];
                                //                 })
                                //                 ->createOptionUsing(function (array $data) {
                                //                     if (! ($data['es_color'] ?? false)) $data['valor'] = $data['nombre'];
                                //                     unset($data['es_color']);
                                //                     return Valor::create($data)->id;
                                //                 })
                                //                 ->hintActions([
                                //                     Action::make('configurar_precios')
                                //                         ->label('Precios Extra')
                                //                         ->icon('heroicon-m-currency-dollar')
                                //                         ->color('warning')
                                //                         ->modalHeading('Precios Adicionales')
                                //                         ->fillForm(function (Get $get) {
                                //                             $selectedIds = $get('valores_seleccionados') ?? [];
                                //                             $currentPrices = $get('extra_prices') ?? [];
                                //                             $valuesData = Valor::whereIn('id', $selectedIds)->get();
                                //                             return [
                                //                                 'precios_repeater' => $valuesData->map(fn($val) => [
                                //                                     'value_id' => $val->id,
                                //                                     'name_display' => $val->nombre,
                                //                                     'extra' => $currentPrices[$val->id] ?? 0,
                                //                                 ])->toArray()
                                //                             ];
                                //                         })
                                //                         ->schema([
                                //                             Repeater::make('precios_repeater')
                                //                                 ->hiddenLabel()
                                //                                 ->addable(false)->deletable(false)->reorderable(false)
                                //                                 ->schema([
                                //                                     TextInput::make('name_display')->label('Opción')->disabled()->columnSpan(1),
                                //                                     Hidden::make('value_id'),
                                //                                     TextInput::make('extra')->label('Precio Extra (S/)')->numeric()->default(0)->prefix('S/')->required()->columnSpan(1),
                                //                                 ])->columns(2)
                                //                         ])
                                //                         ->action(function (array $data, Set $set) {
                                //                             $preciosMapeados = collect($data['precios_repeater'])
                                //                                 ->mapWithKeys(fn($item) => [$item['value_id'] => $item['extra']])
                                //                                 ->toArray();
                                //                             $set('extra_prices', $preciosMapeados);
                                //                         }),

                                //                     Action::make('configurar_valores')
                                //                         ->label('Valores y Exclusiones')
                                //                         ->icon('heroicon-m-cog-6-tooth')
                                //                         ->color('warning')
                                //                         ->modalHeading('Configurar Valores y sus Exclusiones')
                                //                         ->modalWidth('3xl')
                                //                         ->fillForm(function (Get $get, ?Model $record) {
                                //                             $attrId = $get('atributo_id');
                                //                             $valoresSeleccionadosIds = $get('valores_seleccionados') ?? [];

                                //                             if (! $attrId || empty($valoresSeleccionadosIds)) {
                                //                                 return ['lista_valores' => []];
                                //                             }
                                //                             $valores = Valor::whereIn('id', $valoresSeleccionadosIds)->get();
                                //                             $exclusionesGuardadas = $get('exclusiones_guardadas') ?? [];

                                //                             return [
                                //                                 'lista_valores' => $valores->map(fn($v) => [
                                //                                     'id' => $v->id,
                                //                                     'nombre' => $v->nombre,
                                //                                     'exclusiones' => $exclusionesGuardadas[$v->id] ?? [],
                                //                                 ])->toArray(),
                                //                             ];
                                //                         })
                                //                         ->schema([
                                //                             Repeater::make('lista_valores')
                                //                                 ->label('')
                                //                                 ->schema([
                                //                                     Repeater::make('exclusiones')
                                //                                         ->label('Excluir este valor de:')
                                //                                         ->table([
                                //                                             TableColumn::make('Atributo'),
                                //                                             TableColumn::make('Valores'),
                                //                                         ])
                                //                                         ->schema([
                                //                                             Select::make('atributo_id')
                                //                                                 ->label('Atributo')
                                //                                                 ->options(function (?Model $record) {
                                //                                                     $atributosDelProducto = [];
                                //                                                     if ($record) {
                                //                                                         $atributosDelProducto = ProductoAtributo::with('atributo')
                                //                                                             ->where('producto_id', $record->id)
                                //                                                             ->where('estado', 'activo')
                                //                                                             ->get()
                                //                                                             ->mapWithKeys(function ($item) {
                                //                                                                 return [$item->atributo_id => $item->atributo ? $item->atributo->nombre : 'Sin nombre'];
                                //                                                             })
                                //                                                             ->toArray();
                                //                                                     }
                                //                                                     if (empty($atributosDelProducto)) {
                                //                                                         return Atributo::where('empresa_id', Filament::getTenant()->id)
                                //                                                             ->pluck('nombre', 'id')
                                //                                                             ->toArray();
                                //                                                     }
                                //                                                     return $atributosDelProducto;
                                //                                                 })
                                //                                                 ->live()
                                //                                                 ->afterStateUpdated(fn(Set $set) => $set('valor_id', null))
                                //                                                 ->required()
                                //                                                 ->columnSpan(1),

                                //                                             Select::make('valor_id')
                                //                                                 ->label('Valor a excluir')
                                //                                                 ->options(function (Get $get, ?Model $record) {
                                //                                                     $attrIdSeleccionado = $get('atributo_id');
                                //                                                     $valorBaseId = $get('../../id');
                                //                                                     if (! $attrIdSeleccionado) {
                                //                                                         return [];
                                //                                                     }
                                //                                                     $valoresDelProducto = [];
                                //                                                     if ($record) {
                                //                                                         $valoresDelProducto = ProductoAtributoValor::with('valor')
                                //                                                             ->whereHas('productoAtributo', function ($query) use ($record, $attrIdSeleccionado) {
                                //                                                                 $query->where('producto_id', $record->id)
                                //                                                                     ->where('atributo_id', $attrIdSeleccionado)
                                //                                                                     ->where('estado', 'activo');
                                //                                                             })
                                //                                                             ->where('estado', 'activo')
                                //                                                             ->where('valor_id', '!=', $valorBaseId)
                                //                                                             ->get()
                                //                                                             ->mapWithKeys(function ($item) {
                                //                                                                 return [$item->valor_id => $item->valor ? $item->valor->nombre : 'Sin nombre'];
                                //                                                             })
                                //                                                             ->toArray();
                                //                                                     }
                                //                                                     if (empty($valoresDelProducto)) {
                                //                                                         return Valor::where('atributo_id', $attrIdSeleccionado)
                                //                                                             // AQUI TAMBIÉN EVITAMOS QUE SE EXCLUYA A SÍ MISMO
                                //                                                             ->where('id', '!=', $valorBaseId)
                                //                                                             ->pluck('nombre', 'id')
                                //                                                             ->toArray();
                                //                                                     }
                                //                                                     return $valoresDelProducto;
                                //                                                 })
                                //                                                 ->disableOptionWhen(function (Get $get, $value, $state) {
                                //                                                     $todasLasExclusiones = $get('../../exclusiones') ?? [];
                                //                                                     $seleccionados = collect($todasLasExclusiones)->pluck('valor_id')->filter()->toArray();
                                //                                                     if ($value == $state) {
                                //                                                         return false;
                                //                                                     }
                                //                                                     return in_array($value, $seleccionados);
                                //                                                 })
                                //                                                 ->required()
                                //                                                 ->columnSpan(1),
                                //                                         ])
                                //                                         ->columns(2)
                                //                                         ->addActionLabel('Añadir Exclusión')
                                //                                         ->defaultItems(0)
                                //                                         ->reorderable(false),
                                //                                 ])
                                //                                 ->addable(false)
                                //                                 ->deletable(false)
                                //                                 ->reorderable(false)
                                //                                 ->itemLabel(fn(array $state): ?string => $state['nombre'] ?? null),
                                //                         ])
                                //                         ->action(function (array $data, Set $set) {
                                //                             $exclusionesAGuardar = [];
                                //                             foreach ($data['lista_valores'] as $item) {
                                //                                 if (!empty($item['exclusiones'])) {
                                //                                     $exclusionesAGuardar[$item['id']] = $item['exclusiones'];
                                //                                 }
                                //                             }
                                //                             $set('exclusiones_guardadas', $exclusionesAGuardar);
                                //                         }),


                                //                 ]),

                                //             Hidden::make('extra_prices'),
                                //             Hidden::make('exclusiones_guardadas'),

                                //     ])
                                //     ->extraItemActions([
                                //     ])
                                //     ->addActionLabel('Agregar Atributo al Producto')
                                //     ->collapsible()
                                //     ->columnSpanFull(),
                                Repeater::make('atributos')
                                    ->label('Configuración de Variantes')
                                    ->table([
                                        TableColumn::make('Atributos'),
                                        TableColumn::make('Valores'),
                                    ])
                                    ->schema([
                                        // 1. SELECT DE ATRIBUTO
                                        Select::make('atributo_id')
                                            ->label('Atributo (Ej: Tamaño, Color)')
                                            ->options(Atributo::where('empresa_id', Filament::getTenant()->id)->pluck('nombre', 'id'))
                                            ->required()
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
                                            ->form([
                                                Repeater::make('precios_repeater')
                                                    ->hiddenLabel()
                                                    ->addable(false)->deletable(false)->reorderable(false)
                                                    ->schema([
                                                        TextInput::make('name_display')->label('Opción')->disabled()->columnSpan(1),
                                                        Hidden::make('value_id'),
                                                        TextInput::make('extra')->label('Precio Extra (S/)')->numeric()->default(0)->prefix('S/')->required()->columnSpan(1),
                                                    ])->columns(2)
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
                                            ->form([
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
                                    ->columnSpanFull()
                            ]),

                        // --- PESTAÑA 3: CONFIGURACIÓN ---
                        Tab::make('Configuración')
                            ->icon('heroicon-m-cog-6-tooth')
                            ->schema([
                                Section::make('Opciones adicionales del producto')
                                    ->columns(2) // 3 columnas para que los toggles se vean agrupados
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

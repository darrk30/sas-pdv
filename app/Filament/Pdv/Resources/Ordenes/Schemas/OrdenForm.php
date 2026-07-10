<?php

namespace App\Filament\Pdv\Resources\Ordenes\Schemas;

use App\Enums\EstadoOrden;
use App\Enums\TipoDocumento;
use App\Enums\TipoItem;
use App\Models\AjusteDetalle;
use App\Models\Cliente;
use App\Models\MetodoEnvio;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Variante;
use Filament\Facades\Filament;
use Filament\Actions\Action as FormAction;
use Filament\Forms\Components\DateTimePicker;
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

class OrdenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ── Cliente ───────────────────────────────────────────────
                Section::make('Cliente')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([

                        Select::make('cliente_id')
                            ->label('Cliente')
                            ->placeholder('Buscar cliente...')
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->columnSpanFull()
                            ->getSearchResultsUsing(function (string $search): array {
                                $empresa = Filament::getTenant();
                                return Cliente::where('empresa_id', $empresa->id)
                                    ->where(fn($q) => $q
                                        ->where('nombre', 'like', "%{$search}%")
                                        ->orWhere('apellidos', 'like', "%{$search}%")
                                        ->orWhere('numero_documento', 'like', "%{$search}%")
                                    )
                                    ->limit(20)
                                    ->get()
                                    ->mapWithKeys(fn(Cliente $c) => [
                                        $c->id => trim("{$c->nombre} {$c->apellidos}") . " ({$c->numero_documento})",
                                    ])
                                    ->all();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $c = Cliente::find($value);
                                return $c ? trim("{$c->nombre} {$c->apellidos}") . " ({$c->numero_documento})" : null;
                            })
                            ->afterStateUpdated(function (?int $state, Set $set): void {
                                if (! $state) {
                                    $set('cliente_telefono', null);
                                    $set('cliente_direccion', null);
                                    return;
                                }
                                $c = Cliente::find($state);
                                if (! $c) return;
                                $set('cliente_nombre', trim("{$c->nombre} {$c->apellidos}"));
                                $tipodoc = $c->tipo_documento;
                                $set('cliente_tipo_doc', $tipodoc instanceof \BackedEnum ? $tipodoc->value : (string) $tipodoc);
                                $set('cliente_num_doc', $c->numero_documento);
                                $set('cliente_telefono', $c->telefono);
                                $set('cliente_direccion', $c->direccion);
                            })
                            ->createOptionForm([
                                Grid::make(2)->schema([
                                    Select::make('tipo_documento')
                                        ->label('Tipo de documento')
                                        ->options(TipoDocumento::class)
                                        ->native(false)
                                        ->required(),

                                    TextInput::make('numero_documento')
                                        ->label('N° documento')
                                        ->required()
                                        ->maxLength(20),

                                    TextInput::make('nombre')
                                        ->label('Nombre / Razón social')
                                        ->required()
                                        ->maxLength(255),

                                    TextInput::make('apellidos')
                                        ->label('Apellidos')
                                        ->nullable()
                                        ->maxLength(255),

                                    TextInput::make('telefono')
                                        ->label('Teléfono')
                                        ->tel()
                                        ->nullable()
                                        ->maxLength(20),

                                    TextInput::make('correo')
                                        ->label('Correo')
                                        ->email()
                                        ->nullable()
                                        ->maxLength(255),
                                ]),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $data['user_id'] = auth()->id();
                                return Cliente::create($data)->id;
                            }),

                        TextInput::make('cliente_nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('cliente_num_doc')
                            ->label('N° documento')
                            ->nullable()
                            ->maxLength(20),

                        TextInput::make('cliente_tipo_doc')
                            ->label('Tipo documento')
                            ->nullable()
                            ->maxLength(20),

                        TextInput::make('cliente_telefono')
                            ->label('Teléfono')
                            ->tel()
                            ->nullable()
                            ->maxLength(30)
                            ->suffixAction(
                                FormAction::make('whatsapp')
                                    ->icon('heroicon-o-chat-bubble-left-right')
                                    ->color('success')
                                    ->url(function (?string $state): ?string {
                                        if (blank($state)) return null;
                                        $empresa = Filament::getTenant();
                                        $nombreEmpresa = $empresa->nombre ?? $empresa->name ?? 'Nuestra tienda';
                                        $tel = preg_replace('/\D/', '', $state);
                                        if (strlen($tel) === 9) $tel = '51' . $tel;
                                        $msg = urlencode("Hola, te saluda {$nombreEmpresa}. Hemos visto que realizaste un pedido por nuestra web. ¡Estamos aquí para ayudarte!");
                                        return "https://wa.me/{$tel}?text={$msg}";
                                    })
                                    ->openUrlInNewTab()
                            ),

                        TextInput::make('cliente_direccion')
                            ->label('Dirección')
                            ->nullable()
                            ->maxLength(255),

                        Select::make('metodo_pago_id')
                            ->label('Método de pago')
                            ->placeholder('Seleccionar método...')
                            ->searchable()
                            ->nullable()
                            ->options(function (): array {
                                $empresa = Filament::getTenant();
                                return MetodoPago::where('empresa_id', $empresa->id)
                                    ->where('estado', 'activo')
                                    ->orderBy('nombre')
                                    ->get()
                                    ->mapWithKeys(fn(MetodoPago $m) => [
                                        $m->id => $m->nombre,
                                    ])
                                    ->all();
                            }),

                        DateTimePicker::make('fecha_orden')
                            ->label('Fecha de la orden')
                            ->required()
                            ->default(now()),

                    ]),

                // ── Entrega ───────────────────────────────────────────────
                Section::make('Entrega')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([

                        ToggleButtons::make('tipo_entrega')
                            ->label('Tipo de entrega')
                            ->options(['envio' => 'Envío', 'retiro' => 'Retiro en tienda'])
                            ->icons(['envio' => 'heroicon-o-truck', 'retiro' => 'heroicon-o-building-storefront'])
                            ->colors(['envio' => 'info', 'retiro' => 'success'])
                            ->default('envio')
                            ->inline()
                            ->live()
                            ->columnSpanFull()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $val = $state instanceof \BackedEnum ? $state->value : (string) $state;
                                if ($val === 'retiro') {
                                    $set('metodo_envio_id', null);
                                    $set('direccion_agencia', null);
                                    $set('costo_envio', 0);
                                }
                            }),

                        Select::make('metodo_envio_id')
                            ->label('Método de envío')
                            ->placeholder('Seleccionar método...')
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->visible(fn(Get $get): bool => $get('tipo_entrega') === 'envio')
                            ->options(function (): array {
                                $empresa = Filament::getTenant();
                                return MetodoEnvio::where('empresa_id', $empresa->id)
                                    ->where('estado', 'activo')
                                    ->get()
                                    ->mapWithKeys(fn(MetodoEnvio $m) => [
                                        $m->id => "{$m->nombre} (S/ {$m->costo})",
                                    ])
                                    ->all();
                            })
                            ->afterStateUpdated(function (?int $state, Set $set, Get $get): void {
                                if (! $state) {
                                    $set('costo_envio', 0);
                                    self::recalcularTotales($get, $set, false);
                                    return;
                                }
                                $metodo = MetodoEnvio::find($state);
                                $set('costo_envio', $metodo?->costo ?? 0);
                                self::recalcularTotales($get, $set, false);
                            }),

                        TextInput::make('ubicacion_cliente')
                            ->label('Depto / Provincia / Distrito')
                            ->readOnly()
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->visible(fn(Get $get): bool => $get('tipo_entrega') === 'envio'),

                        TextInput::make('direccion_agencia')
                            ->label('Dirección de la agencia')
                            ->placeholder('Ej: Jr. Tacna 123, Cercado de Lima')
                            ->nullable()
                            ->maxLength(255)
                            ->visible(fn(Get $get): bool => $get('tipo_entrega') === 'envio'),

                        TextInput::make('costo_envio')
                            ->label('Costo de envío')
                            ->numeric()
                            ->prefix('S/')
                            ->default(0)
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->visible(fn(Get $get): bool => $get('tipo_entrega') === 'envio')
                            ->afterStateUpdated(fn(Get $get, Set $set) => self::recalcularTotales($get, $set, false)),

                    ]),

                // ── Productos ─────────────────────────────────────────────
                Section::make('Productos')
                    ->schema([
                        Repeater::make('detalles')
                            ->label('')
                            ->relationship('detalles')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::recalcularTotales($get, $set, false);
                            })
                            ->mutateRelationshipDataBeforeCreateUsing(fn(array $data) => self::prepararDetalle($data))
                            ->mutateRelationshipDataBeforeSaveUsing(fn(array $data) => self::prepararDetalle($data))
                            ->table([
                                TableColumn::make('Producto')->width('40%'),
                                TableColumn::make('Cant.'),
                                TableColumn::make('Precio unit.'),
                                TableColumn::make('Descuento'),
                                TableColumn::make('Total'),
                            ])
                            ->schema([

                                Select::make('item_id')
                                    ->label('Producto')
                                    ->placeholder('Buscar producto...')
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->formatStateUsing(function (?Model $record): ?string {
                                        if (! $record) return null;
                                        if ($record->variante_id) return 'variante_' . $record->variante_id;
                                        if ($record->producto_id) return 'producto_' . $record->producto_id;
                                        return null;
                                    })
                                    ->getOptionLabelUsing(function ($value): ?string {
                                        if (blank($value)) return null;
                                        [$tipo, $id] = explode('_', $value, 2);
                                        if ($tipo === 'producto') return Producto::find($id)?->nombre;
                                        $variante = Variante::with(['producto', 'valores.valor'])->find($id);
                                        return $variante ? AjusteDetalle::generarNombre(null, $variante) : null;
                                    })
                                    ->options(function (): array {
                                        $empresa  = Filament::getTenant();
                                        $opciones = [];

                                        $simples = Producto::query()
                                            ->where('empresa_id', $empresa->id)
                                            ->where('estado', 'activo')
                                            ->doesntHave('variantesActivas')
                                            ->get();

                                        foreach ($simples as $producto) {
                                            $opciones["producto_{$producto->id}"] = $producto->nombre;
                                        }

                                        $variantes = Variante::query()
                                            ->with(['producto', 'valores.valor'])
                                            ->where('estado', 'activo')
                                            ->whereHas('producto', fn($q) => $q
                                                ->where('empresa_id', $empresa->id)
                                                ->where('estado', 'activo')
                                            )
                                            ->get();

                                        foreach ($variantes as $variante) {
                                            $opciones["variante_{$variante->id}"] = AjusteDetalle::generarNombre(null, $variante);
                                        }

                                        return $opciones;
                                    })
                                    ->afterStateUpdated(function (?string $state, Set $set): void {
                                        if (blank($state)) {
                                            $set('producto_id', null);
                                            $set('variante_id', null);
                                            $set('descripcion', null);
                                            $set('precio_unitario', null);
                                            $set('cantidad', null);
                                            $set('descuento', 0);
                                            $set('subtotal', 0);
                                            $set('igv', 0);
                                            $set('total', 0);
                                            return;
                                        }

                                        [$tipo, $id] = explode('_', $state, 2);

                                        if ($tipo === 'producto') {
                                            $producto = Producto::find($id);
                                            $set('producto_id', $producto?->id);
                                            $set('variante_id', null);
                                            $set('descripcion', $producto?->nombre);
                                            $set('precio_unitario', $producto?->precio_venta);
                                        } else {
                                            $variante = Variante::with(['producto', 'valores.valor'])->find($id);
                                            $set('producto_id', null);
                                            $set('variante_id', $variante?->id);
                                            $set('descripcion', AjusteDetalle::generarNombre(null, $variante));
                                            $set('precio_unitario', $variante?->precio_final ?? $variante?->producto?->precio_venta);
                                        }

                                        $set('cantidad', null);
                                        $set('descuento', 0);
                                        $set('subtotal', 0);
                                        $set('igv', 0);
                                        $set('total', 0);
                                    }),

                                TextInput::make('cantidad')
                                    ->label('Cant.')
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        self::calcularTotalesItem($get, $set);
                                        self::recalcularTotales($get, $set, true);
                                    }),

                                TextInput::make('precio_unitario')
                                    ->label('Precio unit.')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->minValue(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        self::calcularTotalesItem($get, $set);
                                        self::recalcularTotales($get, $set, true);
                                    }),

                                TextInput::make('descuento')
                                    ->label('Descuento')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->default(0)
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        self::calcularTotalesItem($get, $set);
                                        self::recalcularTotales($get, $set, true);
                                    }),

                                TextInput::make('total')
                                    ->label('Total')
                                    ->prefix('S/')
                                    ->readOnly()
                                    ->numeric()
                                    ->default(0),

                                Hidden::make('producto_id'),
                                Hidden::make('variante_id'),
                                Hidden::make('descripcion'),
                                Hidden::make('valor_unitario'),
                                Hidden::make('subtotal'),
                                Hidden::make('igv'),
                            ])
                            ->addActionLabel('Agregar producto')
                            ->reorderable(false)
                            ->defaultItems(1),
                    ])->columnSpanFull(),

                // ── Totales ───────────────────────────────────────────────
                Section::make('Totales')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('igv')
                                ->label('IGV (18%)')
                                ->prefix('S/')
                                ->readOnly()
                                ->numeric()
                                ->default(0),

                            TextInput::make('subtotal')
                                ->label('Subtotal ítems')
                                ->prefix('S/')
                                ->readOnly()
                                ->numeric()
                                ->default(0),

                            TextInput::make('costo_envio')
                                ->label('Envío')
                                ->prefix('S/')
                                ->readOnly()
                                ->numeric()
                                ->default(0)
                                ->visible(fn(Get $get): bool => $get('tipo_entrega') === 'envio'),

                            TextInput::make('total')
                                ->label('Total')
                                ->prefix('S/')
                                ->readOnly()
                                ->numeric()
                                ->default(0)
                                ->extraAttributes(['class' => 'font-bold']),
                        ]),
                    ]),

                // ── Estado y notas ────────────────────────────────────────
                Section::make('Estado y notas')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([

                        Select::make('estado')
                            ->label('Estado')
                            ->options(EstadoOrden::class)
                            ->required()
                            ->native(false)
                            ->default(EstadoOrden::PendientePago->value)
                            ->disabled(),

                        Textarea::make('notas')
                            ->label('Notas (visibles al cliente)')
                            ->nullable()
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('notas_internas')
                            ->label('Notas internas')
                            ->nullable()
                            ->rows(2)
                            ->columnSpanFull(),

                    ]),

            ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private static function calcularTotalesItem(Get $get, Set $set): void
    {
        $cantidad  = (float) ($get('cantidad') ?? 0);
        $precio    = (float) ($get('precio_unitario') ?? 0);
        $descuento = (float) ($get('descuento') ?? 0);

        if ($cantidad <= 0 || $precio < 0) return;

        $total        = round($precio * $cantidad - $descuento, 2);
        $valorUnitario = round($precio / 1.18, 4);
        $subtotal      = round($cantidad * $valorUnitario, 2);
        $igv           = round($total - $subtotal, 2);

        $set('total', max(0, $total));
        $set('subtotal', max(0, $subtotal));
        $set('igv', max(0, $igv));
        $set('valor_unitario', $valorUnitario);
    }

    private static function recalcularTotales(Get $get, Set $set, bool $isInsideRepeater): void
    {
        $detalles = $isInsideRepeater ? $get('../../detalles') : $get('detalles');

        $sumaItems = 0.0;
        $sumaIgv   = 0.0;

        if (is_array($detalles)) {
            foreach ($detalles as $item) {
                $sumaItems += (float) ($item['total'] ?? 0);
                $sumaIgv   += (float) ($item['igv'] ?? 0);
            }
        }

        $prefix     = $isInsideRepeater ? '../../' : '';
        $costoEnvio = (float) ($isInsideRepeater ? $get('../../costo_envio') : $get('costo_envio'));

        $set($prefix . 'subtotal', round($sumaItems, 2));
        $set($prefix . 'igv', round($sumaIgv, 2));
        $set($prefix . 'total', round($sumaItems + $costoEnvio, 2));
    }

    private static function prepararDetalle(array $data): array
    {
        unset($data['item_id']);

        $cantidad  = (float) ($data['cantidad'] ?? 0);
        $precio    = (float) ($data['precio_unitario'] ?? 0);
        $descuento = (float) ($data['descuento'] ?? 0);

        $total         = round($precio * $cantidad - $descuento, 2);
        $valorUnitario = round($precio / 1.18, 4);
        $subtotal      = round($cantidad * $valorUnitario, 2);
        $igv           = round($total - $subtotal, 2);

        $data['total']          = max(0, $total);
        $data['subtotal']       = max(0, $subtotal);
        $data['igv']            = max(0, $igv);
        $data['valor_unitario'] = $valorUnitario;

        $data['tipo_item'] = ! empty($data['variante_id'])
            ? TipoItem::Variante->value
            : TipoItem::Producto->value;

        if (empty($data['descripcion'])) {
            if (! empty($data['producto_id'])) {
                $data['descripcion'] = Producto::find($data['producto_id'])?->nombre ?? '';
            } elseif (! empty($data['variante_id'])) {
                $variante = Variante::with(['producto', 'valores.valor'])->find($data['variante_id']);
                $data['descripcion'] = $variante ? AjusteDetalle::generarNombre(null, $variante) : '';
            }
        }

        return $data;
    }
}

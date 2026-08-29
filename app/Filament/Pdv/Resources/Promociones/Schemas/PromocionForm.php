<?php

namespace App\Filament\Pdv\Resources\Promociones\Schemas;

use App\Enums\EstadoPromocion;
use App\Models\AjusteDetalle;
use App\Models\Producto;
use App\Models\Variante;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class PromocionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Tabs::make()->tabs([

                // ── TAB: INFORMACIÓN ─────────────────────────────────────
                Tab::make('Información')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([

                            FileUpload::make('imagen')
                                ->label('Imagen')
                                ->image()
                                ->directory('promociones')
                                ->optimize('webp', 85)
                                ->maxImageWidth(1200)
                                ->imageEditor()
                                ->nullable()
                                ->columnSpanFull(),

                            TextInput::make('nombre')
                                ->label('Nombre')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Textarea::make('descripcion')
                                ->label('Descripción')
                                ->rows(3)
                                ->maxLength(500)
                                ->columnSpanFull(),

                            TextInput::make('precio')
                                ->label('Precio de venta (S/ con IGV)')
                                ->numeric()
                                ->required()
                                ->minValue(0.01)
                                ->prefix('S/')
                                ->placeholder('0.00'),

                            ToggleButtons::make('estado')
                                ->label('Estado')
                                ->options(EstadoPromocion::class)
                                ->inline()
                                ->required()
                                ->default(EstadoPromocion::Activo),

                            DatePicker::make('fecha_inicio')
                                ->label('Válida desde')
                                ->native(false)
                                ->placeholder('Sin límite inicial'),

                            DatePicker::make('fecha_fin')
                                ->label('Válida hasta')
                                ->native(false)
                                ->placeholder('Sin límite final')
                                ->afterOrEqual('fecha_inicio'),

                        ]),
                    ]),

                // ── TAB: PRODUCTOS ───────────────────────────────────────
                Tab::make('Productos del combo')
                    ->icon('heroicon-o-shopping-bag')
                    ->schema([
                        Repeater::make('detalles')
                            ->label('')
                            ->relationship('detalles')
                            ->live()
                            ->mutateRelationshipDataBeforeCreateUsing(
                                fn(array $data) => collect($data)->except('item_id')->toArray()
                            )
                            ->mutateRelationshipDataBeforeSaveUsing(
                                fn(array $data) => collect($data)->except('item_id')->toArray()
                            )
                            ->table([
                                TableColumn::make('Producto / Variante'),
                                TableColumn::make('Cantidad'),
                            ])
                            ->schema([

                                Select::make('item_id')
                                    ->label('Producto / Variante')
                                    ->placeholder('Buscar por nombre, código interno o código de barras...')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->formatStateUsing(function (?Model $record) {
                                        if (! $record) return null;
                                        if ($record->variante_id) return 'variante_' . $record->variante_id;
                                        if ($record->producto_id) return 'producto_' . $record->producto_id;
                                        return null;
                                    })
                                    ->getOptionLabelUsing(function ($value): ?string {
                                        if (blank($value)) return null;
                                        [$tipo, $id] = explode('_', $value, 2);

                                        if ($tipo === 'producto') {
                                            $p   = Producto::find($id);
                                            $tag = $p?->codigo_interno ?: $p?->codigo_barras;
                                            return $p ? ($tag ? "[{$tag}] {$p->nombre}" : $p->nombre) : null;
                                        }

                                        $variante = Variante::with(['producto', 'valores.valor', 'valores.productoAtributo.atributo'])->find($id);
                                        if (! $variante) return null;
                                        $nombre = AjusteDetalle::generarNombre(null, $variante);
                                        return $variante->codigo ? "[{$variante->codigo}] {$nombre}" : $nombre;
                                    })
                                    ->options(fn() => self::_buscarItems('', Filament::getTenant()?->id))
                                    ->getSearchResultsUsing(fn(string $search) => self::_buscarItems($search, Filament::getTenant()?->id))
                                    ->afterStateUpdated(function (?string $state, Set $set): void {
                                        if (blank($state)) {
                                            $set('producto_id', null);
                                            $set('variante_id', null);
                                            return;
                                        }

                                        [$tipo, $id] = explode('_', $state, 2);

                                        if ($tipo === 'producto') {
                                            $set('producto_id', (int) $id);
                                            $set('variante_id', null);
                                        } else {
                                            $set('producto_id', null);
                                            $set('variante_id', (int) $id);
                                        }
                                    }),

                                TextInput::make('cantidad')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(0.001),

                                Hidden::make('producto_id'),
                                Hidden::make('variante_id'),

                            ])
                            ->addActionLabel('Agregar producto')
                            ->reorderable(false)
                            ->defaultItems(1)
                            ->minItems(1),
                    ]),

                // ── TAB: REGLAS ──────────────────────────────────────────
                Tab::make('Reglas')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([

                            TextInput::make('codigo_promo')
                                ->label('Código de canje')
                                ->maxLength(20)
                                ->placeholder('Ej: COMBO10')
                                ->helperText('Dejar en blanco si no requiere código.'),

                            TextInput::make('limite_usos')
                                ->label('Límite de usos')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->placeholder('Sin límite')
                                ->helperText('Máximo de veces que se puede canjear en total.'),

                        ]),

                        CheckboxList::make('dias_semana')
                            ->label('Días de la semana disponibles')
                            ->options([
                                '1' => 'Lunes',
                                '2' => 'Martes',
                                '3' => 'Miércoles',
                                '4' => 'Jueves',
                                '5' => 'Viernes',
                                '6' => 'Sábado',
                                '7' => 'Domingo',
                            ])
                            ->columns(4)
                            ->helperText('Dejar todo sin marcar si aplica cualquier día.'),
                    ]),

            ])->columnSpanFull(),

        ]);
    }

    private static function _buscarItems(string $search, ?int $empresaId): array
    {
        $opciones  = [];
        $esPreload = blank($search);
        $limite    = $esPreload ? 50 : 25;

        $simples = Producto::query()
            ->doesntHave('variantesActivas')
            ->whereIn('estado', ['activo', 'inactivo'])
            ->when($empresaId, fn($q) => $q->where('empresa_id', $empresaId))
            ->when(! $esPreload, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('codigo_interno', 'like', "%{$search}%")
                  ->orWhere('codigo_barras', 'like', "%{$search}%");
            }))
            ->orderBy('nombre')
            ->limit($limite)
            ->get();

        foreach ($simples as $p) {
            $tag   = $p->codigo_interno ?: $p->codigo_barras;
            $label = $tag ? "[{$tag}] {$p->nombre}" : $p->nombre;
            if ($p->estado === 'inactivo') $label .= ' (inactivo)';
            $opciones["producto_{$p->id}"] = $label;
        }

        $variantes = Variante::query()
            ->with(['producto', 'valores.valor', 'valores.productoAtributo.atributo'])
            ->join('productos as p_ord', 'p_ord.id', '=', 'variantes.producto_id')
            ->select('variantes.*')
            ->whereIn('variantes.estado', ['activo', 'inactivo'])
            ->whereIn('p_ord.estado', ['activo', 'inactivo'])
            ->when($empresaId, fn($q) => $q->where('p_ord.empresa_id', $empresaId))
            ->when(! $esPreload, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('variantes.codigo', 'like', "%{$search}%")
                  ->orWhere('p_ord.nombre', 'like', "%{$search}%")
                  ->orWhere('p_ord.codigo_interno', 'like', "%{$search}%")
                  ->orWhere('p_ord.codigo_barras', 'like', "%{$search}%");
            }))
            ->orderBy('p_ord.nombre')
            ->limit($limite)
            ->get();

        foreach ($variantes as $v) {
            $nombre = AjusteDetalle::generarNombre(null, $v);
            $label  = $v->codigo ? "[{$v->codigo}] {$nombre}" : $nombre;
            if ($v->estado === 'inactivo') $label .= ' (inactivo)';
            $opciones["variante_{$v->id}"] = $label;
        }

        return $opciones;
    }
}

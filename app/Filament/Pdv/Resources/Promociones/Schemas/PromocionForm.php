<?php

namespace App\Filament\Pdv\Resources\Promociones\Schemas;

use App\Models\AjusteDetalle;
use App\Models\Producto;
use App\Models\Variante;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class PromocionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // ── INFORMACIÓN GENERAL ──────────────────────────────────────
            Section::make('Información general')
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([

                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('precio')
                            ->label('Precio de venta (S/ con IGV)')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->prefix('S/')
                            ->placeholder('0.00'),

                        FileUpload::make('imagen')
                            ->label('Imagen')
                            ->image()
                            ->directory('promociones')
                            ->imageEditor()
                            ->nullable(),

                        TextInput::make('descripcion')
                            ->label('Descripción')
                            ->maxLength(500)
                            ->columnSpanFull(),

                    ]),
                ])->columnSpanFull(),

            // ── PRODUCTOS DEL COMBO ──────────────────────────────────────
            Section::make('Productos que incluye el combo')
                ->description('Selecciona cada producto o variante y la cantidad que incluye la promoción.')
                ->schema([
                    Repeater::make('detalles')
                        ->relationship('detalles')
                        ->label('')
                        ->mutateRelationshipDataBeforeCreateUsing(
                            fn(array $data) => collect($data)->except('item_id')->toArray()
                        )
                        ->mutateRelationshipDataBeforeSaveUsing(
                            fn(array $data) => collect($data)->except('item_id')->toArray()
                        )
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([

                                // ── Select unificado producto simple / variante ──
                                Select::make('item_id')
                                    ->label('Producto / Variante')
                                    ->placeholder('Buscar producto...')
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->columnSpanFull()
                                    // Reconstruye el valor al editar un registro existente
                                    ->formatStateUsing(function (?Model $record): ?string {
                                        if (! $record) {
                                            return null;
                                        }
                                        if ($record->variante_id) {
                                            return 'variante_' . $record->variante_id;
                                        }
                                        if ($record->producto_id) {
                                            return 'producto_' . $record->producto_id;
                                        }
                                        return null;
                                    })
                                    ->getOptionLabelUsing(function ($value): ?string {
                                        if (blank($value)) {
                                            return null;
                                        }
                                        [$tipo, $id] = explode('_', $value, 2);

                                        if ($tipo === 'producto') {
                                            return Producto::find($id)?->nombre;
                                        }

                                        $variante = Variante::with(['producto', 'valores.valor'])->find($id);
                                        return $variante
                                            ? AjusteDetalle::generarNombre(null, $variante)
                                            : null;
                                    })
                                    ->options(function (): array {
                                        $empresaId = Filament::getTenant()->id;
                                        $opciones  = [];

                                        // Productos simples (sin variantes)
                                        Producto::where('empresa_id', $empresaId)
                                            ->doesntHave('variantes')
                                            ->where('estado', '!=', 'archivado')
                                            ->orderBy('nombre')
                                            ->get()
                                            ->each(function ($p) use (&$opciones) {
                                                $opciones["producto_{$p->id}"] = $p->nombre;
                                            });

                                        // Variantes
                                        Variante::with(['producto', 'valores.valor'])
                                            ->whereHas('producto', fn($q) => $q
                                                ->where('empresa_id', $empresaId)
                                                ->where('estado', '!=', 'archivado'))
                                            ->get()
                                            ->each(function ($v) use (&$opciones) {
                                                $opciones["variante_{$v->id}"] = AjusteDetalle::generarNombre(null, $v);
                                            });

                                        return $opciones;
                                    })
                                    ->afterStateUpdated(function (?string $state, $set): void {
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
                                    ->minValue(0.001)
                                    ->step(1),

                                // Campos ocultos que se guardan en BD
                                Hidden::make('producto_id'),
                                Hidden::make('variante_id'),

                            ]),
                        ])
                        ->addActionLabel('Agregar producto')
                        ->defaultItems(1)
                        ->reorderable(false)
                        ->minItems(1),
                ])->columnSpanFull(),

            // ── REGLAS DE DISPONIBILIDAD ─────────────────────────────────
            Section::make('Reglas de disponibilidad')
                ->description('Todas las condiciones configuradas deben cumplirse para que la promoción sea válida. Las que se dejen en blanco no aplican.')
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

                    Toggle::make('estado')
                        ->label('Promoción activa')
                        ->default(true)
                        ->inline(false),

                ])->columnSpanFull(),

        ]);
    }
}

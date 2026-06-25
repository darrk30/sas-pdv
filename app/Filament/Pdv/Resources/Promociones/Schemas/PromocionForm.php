<?php

namespace App\Filament\Pdv\Resources\Promociones\Schemas;

use App\Models\Producto;
use App\Models\Variante;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                ->description('Define qué productos y cantidades forman esta promoción.')
                ->schema([
                    Repeater::make('detalles')
                        ->relationship('detalles')
                        ->label('')
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 3])->schema([

                                Select::make('producto_id')
                                    ->label('Producto')
                                    ->options(fn() => Producto::where('empresa_id', Filament::getTenant()->id)
                                        ->where('estado', 'activo')
                                        ->orderBy('nombre')
                                        ->pluck('nombre', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(fn($set) => $set('variante_id', null)),

                                Select::make('variante_id')
                                    ->label('Variante')
                                    ->options(function ($get) {
                                        $productoId = $get('producto_id');
                                        if (! $productoId) {
                                            return [];
                                        }

                                        return Variante::where('producto_id', $productoId)
                                            ->where('estado', true)
                                            ->with('valores.productoAtributoValor')
                                            ->get()
                                            ->mapWithKeys(fn($v) => [
                                                $v->id => $v->codigo,
                                            ]);
                                    })
                                    ->native(false)
                                    ->searchable()
                                    ->placeholder('Sin variante')
                                    ->visible(fn($get) => $get('producto_id') &&
                                        Variante::where('producto_id', $get('producto_id'))->exists()),

                                TextInput::make('cantidad')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(0.001)
                                    ->step(1),

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

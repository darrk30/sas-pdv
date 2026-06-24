<?php

namespace App\Filament\Pdv\Resources\SesionCajas\Schemas;

use App\Enums\EstadoSesion;
use App\Models\Caja;
use App\Models\MetodoPago;
use App\Models\SesionCaja;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SesionCajaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ── APERTURA ─────────────────────────────────────────────
                Section::make('Apertura de Caja')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([

                            Select::make('caja_id')
                                ->label('Caja')
                                ->options(function () {
                                    return Caja::where('empresa_id', Filament::getTenant()->id)
                                        ->where('estado', true)
                                        ->whereHas('usuarios', fn($q) => $q->where('user_id', auth()->id()))
                                        ->pluck('nombre', 'id');
                                })
                                ->default(function () {
                                    $cajas = Caja::where('empresa_id', Filament::getTenant()->id)
                                        ->where('estado', true)
                                        ->whereHas('usuarios', fn($q) => $q->where('user_id', auth()->id()))
                                        ->pluck('id');

                                    return $cajas->count() === 1 ? $cajas->first() : null;
                                })
                                ->native(false)
                                ->required()
                                ->searchable(),

                            DateTimePicker::make('fecha_apertura')
                                ->label('Fecha y hora de apertura')
                                ->required()
                                ->default(now())
                                ->native(false),

                        ]),
                    ])->columnSpanFull(),

                // ── ESTADO ───────────────────────────────────────────────
                Section::make('Estado')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([

                            ToggleButtons::make('estado')
                                ->label('Estado de la sesión')
                                ->options(EstadoSesion::class)
                                ->inline()
                                ->required()
                                ->default(EstadoSesion::Abierta)
                                ->live(),

                            DateTimePicker::make('fecha_cierre')
                                ->label('Fecha y hora de cierre')
                                ->native(false)
                                ->visible(fn($get) => self::esCerrada($get('estado'))),

                        ]),
                    ])->columnSpanFull(),

                // ── CIERRE ───────────────────────────────────────────────
                Section::make('Cierre de Caja')
                    ->visible(fn($get) => self::esCerrada($get('estado')))
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])->schema([

                            TextInput::make('total_sistema')
                                ->label('Total sistema (S/)')
                                ->numeric()
                                ->minValue(0)
                                ->placeholder('0.00'),

                            TextInput::make('total_cajero')
                                ->label('Total cajero (S/)')
                                ->numeric()
                                ->minValue(0)
                                ->placeholder('0.00')
                                ->live(debounce: 500)
                                ->afterStateUpdated(function ($state, $get, $set) {
                                    $sistema = (float) ($get('total_sistema') ?? 0);
                                    $cajero  = (float) ($state ?? 0);
                                    $set('diferencia_total', round($cajero - $sistema, 2));
                                }),

                            TextInput::make('diferencia_total')
                                ->label('Diferencia (S/)')
                                ->numeric()
                                ->readOnly()
                                ->placeholder('0.00'),

                        ]),

                        Textarea::make('notas_cierre')
                            ->label('Notas de cierre')
                            ->nullable()
                            ->rows(3)
                            ->placeholder('Observaciones al cerrar la caja...'),
                    ])->columnSpanFull(),

                // ── MÉTODOS DE PAGO ──────────────────────────────────────
                Section::make('Desglose por Método de Pago')
                    ->visible(fn($get) => self::esCerrada($get('estado')))
                    ->schema([
                        Repeater::make('pagos')
                            ->relationship('pagos')
                            ->label('')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 4])->schema([

                                    Select::make('metodo_pago_id')
                                        ->label('Método de pago')
                                        ->options(fn() => MetodoPago::where('empresa_id', Filament::getTenant()->id)
                                            ->where('estado', 'activo')
                                            ->pluck('nombre', 'id'))
                                        ->native(false)
                                        ->required()
                                        ->searchable(),

                                    TextInput::make('importe_sistema')
                                        ->label('Importe sistema (S/)')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0),

                                    TextInput::make('importe_cajero')
                                        ->label('Importe cajero (S/)')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->live(debounce: 500)
                                        ->afterStateUpdated(function ($state, $get, $set) {
                                            $sistema = (float) ($get('importe_sistema') ?? 0);
                                            $cajero  = (float) ($state ?? 0);
                                            $set('diferencia', round($cajero - $sistema, 2));
                                        }),

                                    TextInput::make('diferencia')
                                        ->label('Diferencia (S/)')
                                        ->numeric()
                                        ->readOnly()
                                        ->default(0),

                                ]),
                            ])
                            ->addActionLabel('Agregar método de pago')
                            ->defaultItems(0)
                            ->reorderable(false),
                    ])->columnSpanFull(),

            ]);
    }

    private static function esCerrada(mixed $estado): bool
    {
        if ($estado instanceof EstadoSesion) {
            return $estado === EstadoSesion::Cerrada;
        }
        return (string) $estado === EstadoSesion::Cerrada->value;
    }
}

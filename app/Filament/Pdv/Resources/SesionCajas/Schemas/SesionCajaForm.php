<?php

namespace App\Filament\Pdv\Resources\SesionCajas\Schemas;

use App\Models\Caja;
use App\Models\MetodoPago;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SesionCajaForm
{
    // Formulario simple: solo para abrir una caja nueva
    public static function configureApertura(Schema $schema): Schema
    {
        return $schema->components([
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
        ]);
    }

    // Formulario de cierre: montos sistema read-only, solo cajero edita sus montos
    public static function configureCierre(Schema $schema): Schema
    {
        return $schema->components([

            // ── INFORMACIÓN DE APERTURA (solo lectura) ──────────────────
            Section::make('Sesión en curso')
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([

                        Select::make('caja_id')
                            ->label('Caja')
                            ->options(fn() => Caja::where('empresa_id', Filament::getTenant()->id)
                                ->pluck('nombre', 'id'))
                            ->disabled()
                            ->native(false),

                        DateTimePicker::make('fecha_apertura')
                            ->label('Fecha y hora de apertura')
                            ->disabled()
                            ->native(false),

                    ]),
                ])->columnSpanFull(),

            // ── TOTAL DEL SISTEMA ────────────────────────────────────────
            Section::make('Resumen del sistema')
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([

                        TextInput::make('total_sistema')
                            ->label('Total sistema (S/)')
                            ->numeric()
                            ->readOnly()
                            ->helperText('Calculado automáticamente de las transacciones aprobadas.'),

                        Textarea::make('notas_cierre')
                            ->label('Notas de cierre')
                            ->rows(3)
                            ->placeholder('Observaciones al cerrar la caja...'),

                    ]),
                ])->columnSpanFull(),

            // ── DESGLOSE POR MÉTODO DE PAGO ─────────────────────────────
            Section::make('Conteo por método de pago')
                ->description('Ingresa el monto que contaste físicamente en cada método de pago.')
                ->schema([
                    Repeater::make('pagos')
                        ->relationship('pagos')
                        ->label('')
                        ->schema([
                            Grid::make(['default' => 2, 'md' => 4])->schema([

                                Select::make('metodo_pago_id')
                                    ->label('Método de pago')
                                    ->options(fn() => MetodoPago::where('empresa_id', Filament::getTenant()->id)
                                        ->pluck('nombre', 'id'))
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->native(false),

                                TextInput::make('importe_sistema')
                                    ->label('Sistema (S/)')
                                    ->numeric()
                                    ->readOnly()
                                    ->helperText('No editable.'),

                                TextInput::make('importe_cajero')
                                    ->label('Cajero (S/)')
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
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false),
                ])->columnSpanFull(),

        ]);
    }
}

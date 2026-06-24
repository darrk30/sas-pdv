<?php

namespace App\Filament\Pdv\Resources\IngresoEgresos\Schemas;

use App\Enums\CategoriaEgreso;
use App\Enums\TipoMovimiento;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IngresoEgresoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Movimiento de Caja')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([

                            ToggleButtons::make('tipo')
                                ->label('Tipo')
                                ->options(TipoMovimiento::class)
                                ->inline()
                                ->required()
                                ->default(TipoMovimiento::Egreso)
                                ->live()
                                ->columnSpanFull(),

                            DateTimePicker::make('fecha_hora')
                                ->label('Fecha y hora')
                                ->required()
                                ->default(now())
                                ->native(false),

                            // Solo egreso: categoría
                            Select::make('categoria')
                                ->label('Categoría')
                                ->options(CategoriaEgreso::class)
                                ->native(false)
                                ->required()
                                ->live()
                                ->visible(fn($get) => self::tipo($get('tipo')) === TipoMovimiento::Egreso->value),

                            // Nombre libre: ingreso siempre, egreso solo si categoría ≠ remuneración
                            TextInput::make('entregado_a')
                                ->label(fn($get) => self::tipo($get('tipo')) === TipoMovimiento::Ingreso->value
                                    ? 'Nombre de quien entrega'
                                    : 'Entregado a')
                                ->maxLength(255)
                                ->visible(fn($get) => self::tipo($get('tipo')) === TipoMovimiento::Ingreso->value
                                    || (self::tipo($get('tipo')) === TipoMovimiento::Egreso->value
                                        && self::categoria($get('categoria')) !== CategoriaEgreso::Remuneracion->value)),

                            // Select usuario: egreso + remuneración
                            Select::make('user_receptor_id')
                                ->label('Usuario (receptor)')
                                ->options(fn() => User::whereHas(
                                    'empresas',
                                    fn($q) => $q->where('empresas.id', Filament::getTenant()->id)
                                )->pluck('name', 'id'))
                                ->native(false)
                                ->searchable()
                                ->required()
                                ->visible(fn($get) => self::tipo($get('tipo')) === TipoMovimiento::Egreso->value
                                    && self::categoria($get('categoria')) === CategoriaEgreso::Remuneracion->value),

                            TextInput::make('monto')
                                ->label('Monto (S/)')
                                ->numeric()
                                ->minValue(0.01)
                                ->required()
                                ->placeholder('0.00'),

                            Textarea::make('motivo')
                                ->label('Motivo')
                                ->required()
                                ->rows(3)
                                ->columnSpanFull()
                                ->placeholder('Descripción del movimiento...'),

                        ]),
                    ])->columnSpanFull(),
            ]);
    }

    private static function tipo(mixed $v): string
    {
        return $v instanceof TipoMovimiento ? $v->value : (string) $v;
    }

    private static function categoria(mixed $v): string
    {
        return $v instanceof CategoriaEgreso ? $v->value : (string) $v;
    }
}

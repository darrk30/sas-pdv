<?php

namespace App\Filament\Pdv\Resources\Cajas\Schemas;

use App\Models\Caja;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CajaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la Caja')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'lg' => 2,
                        ])->schema([
                            TextInput::make('nombre')
                                ->label('Nombre de la Caja')
                                ->required()
                                ->placeholder('Ej: Caja Principal')
                                ->maxLength(255),

                            TextInput::make('codigo')
                                ->label('Código Único')
                                ->required()
                                ->placeholder('Ej: CAJA-01')
                                ->maxLength(50)
                                ->default(function () {
                                    $ultimaCaja = Caja::where('empresa_id', Filament::getTenant()->id)
                                        ->latest('id')
                                        ->first();
                                    $nuevoId = $ultimaCaja ? $ultimaCaja->id + 1 : 1;
                                    return 'CAJA-' . str_pad($nuevoId, 3, '0', STR_PAD_LEFT);
                                })
                                ->unique(
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn($rule) => $rule->where('empresa_id', Filament::getTenant()->id)
                                )
                                ->validationMessages([
                                    'unique' => 'Ya existe una caja con este código en la empresa.',
                                ]),

                            Toggle::make('estado')
                                ->label('Estado')
                                ->default(true)
                                ->helperText('Si se desactiva, no aparecerá en el punto de venta.'),
                        ])
                    ])->columnSpanFull(),
            ]);
    }
}

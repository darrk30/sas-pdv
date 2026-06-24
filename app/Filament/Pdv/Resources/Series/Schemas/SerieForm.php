<?php

namespace App\Filament\Pdv\Resources\Series\Schemas;

use App\Enums\TipoComprobante;
use App\Models\Serie;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SerieForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la Serie')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([

                            Select::make('tipo')
                                ->label('Tipo de Comprobante')
                                ->options(TipoComprobante::class)
                                ->native(false)
                                ->required(),

                            TextInput::make('serie')
                                ->label('Serie')
                                ->required()
                                ->maxLength(20)
                                ->placeholder('Ej: F001')
                                ->unique(
                                    table: Serie::class,
                                    column: 'serie',
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn($rule) => $rule->where('empresa_id', Filament::getTenant()->id)
                                )
                                ->validationMessages([
                                    'unique' => 'Ya existe esta serie en la empresa.',
                                ]),

                            TextInput::make('numero')
                                ->label('Número actual')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->required()
                                ->helperText('Siguiente correlativo que se asignará al emitir un comprobante.'),

                            Toggle::make('estado')
                                ->label('Activa')
                                ->default(true),

                        ]),
                    ])->columnSpanFull(),
            ]);
    }
}

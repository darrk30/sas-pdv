<?php

namespace App\Filament\Pdv\Resources\Dimensions\RelationManagers;

use App\Filament\Pdv\Resources\Dimensions\DimensionResource;
use App\Models\UnidadesMedida;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UnidadesMedidaRelationManager extends RelationManager
{
    protected static string $relationship = 'unidadesMedida';

    protected static ?string $relatedResource = DimensionResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Unidad')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('simbolo')
                    ->label('Símbolo')
                    ->badge(),

                IconColumn::make('es_base')
                    ->label('Es Base')
                    ->boolean(),

                TextColumn::make('unidadPadre.nombre')
                    ->label('Depende de')
                    ->placeholder('N/A (Es base)'),

                TextColumn::make('factor_conversion')
                    ->label('Factor')
                    ->numeric(decimalPlaces: 4),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        // Aseguramos que se inyecte el ID de la empresa al crear
                        $data['empresa_id'] = Filament::getTenant()->id;
                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading(fn(UnidadesMedida $record) => 'Editando a ' . $record->nombre)
                    ->hidden(fn(UnidadesMedida $record) => strtolower($record->nombre) === 'unidad'),

                DeleteAction::make()
                    ->modalHeading(fn(UnidadesMedida $record) => 'Eliminar a ' . $record->nombre)
                    ->hidden(fn(UnidadesMedida $record) => strtolower($record->nombre) === 'unidad'),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('nombre')
                    ->label('Nombre (Ej. Caja, Kilo, Gramo)')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(1),

                TextInput::make('simbolo')
                    ->label('Símbolo (Ej. kg, g, cj)')
                    ->required()
                    ->maxLength(10)
                    ->columnSpan(1),

                // 🌟 Lógica Reactiva de Unidad Base
                Toggle::make('es_base')
                    ->label('¿Es la unidad base?')
                    ->helperText('Actívalo si es la medida más pequeña de esta dimensión (Ej. Gramo o Unidad).')
                    ->default(false)
                    ->live() // Hace que el formulario reaccione al instante
                    ->columnSpanFull(),

                // 🌟 Selector de Padre (Solo visible si NO es base)
                Select::make('unidad_base_id')
                    ->label('Unidad de Referencia')
                    ->placeholder('¿De qué unidad depende?')
                    ->hidden(fn($get) => $get('es_base')) // Oculta si es la base
                    ->required(fn($get) => !$get('es_base')) // Exige este campo si no es la base
                    ->relationship(
                        name: 'unidadPadre',
                        titleAttribute: 'nombre',
                        modifyQueryUsing: fn($query) => $query->where('dimension_id', $this->getOwnerRecord()->id)
                    )
                    ->searchable()
                    ->preload(),

                TextInput::make('factor_conversion')
                    ->label('Factor de Conversión')
                    ->numeric()
                    ->default(1)
                    ->disabled(fn($get) => $get('es_base')) // Bloquea la escritura si es base
                    ->dehydrated() // Asegura que el número 1 se envíe a la base de datos aunque esté bloqueado
                    ->helperText(fn($get) => $get('es_base')
                        ? 'Al ser unidad base, el factor es siempre 1.'
                        : '¿A cuántas unidades de referencia equivale esta?'),
            ]);
    }
}

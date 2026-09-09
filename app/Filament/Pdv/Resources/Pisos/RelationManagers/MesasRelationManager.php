<?php

namespace App\Filament\Pdv\Resources\Pisos\RelationManagers;

use App\Enums\EstadoMesa;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class MesasRelationManager extends RelationManager
{
    protected static string $relationship = 'mesas';

    protected static ?string $title = 'Mesas';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 2])->schema([

                TextInput::make('nombre')
                    ->label('Nombre / Número')
                    ->required()
                    ->placeholder('Ej: Mesa 1, Mesa VIP, Barra 1')
                    ->maxLength(50),

                TextInput::make('capacidad')
                    ->label('Capacidad (personas)')
                    ->numeric()
                    ->default(4)
                    ->minValue(1)
                    ->maxValue(99),

                TextInput::make('orden')
                    ->label('Orden')
                    ->numeric()
                    ->default(0)
                    ->helperText('Menor número = aparece primero'),

                Toggle::make('estado')
                    ->label('Mesa activa')
                    ->default(true)
                    ->helperText('Las mesas inactivas no aparecen en el mapa.'),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('orden')
                    ->label('#')
                    ->sortable()
                    ->width(50),

                TextColumn::make('nombre')
                    ->label('Mesa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('capacidad')
                    ->label('Capacidad')
                    ->suffix(' pers.')
                    ->alignCenter(),

                TextColumn::make('estado_ocupacion')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (EstadoMesa $state): string => $state->getColor()),

                ToggleColumn::make('estado')
                    ->label('Activa')
                    ->disabled(fn () => ! auth()->user()?->can('mesas.editar')),
            ])
            ->defaultSort('orden')
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['empresa_id'] = $this->getOwnerRecord()->empresa_id;
                        $data['estado_ocupacion'] = EstadoMesa::Libre->value;
                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn ($record) => $record->estado_ocupacion !== EstadoMesa::Libre),
            ]);
    }
}

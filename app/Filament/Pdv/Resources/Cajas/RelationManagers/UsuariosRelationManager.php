<?php

namespace App\Filament\Pdv\Resources\Cajas\RelationManagers;

use App\Models\Turno;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Illuminate\Support\Collection;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsuariosRelationManager extends RelationManager
{
    protected static string $relationship = 'usuarios';
    protected static ?string $modelLabel = 'Cajero';
    protected static ?string $pluralModelLabel = 'Cajeros';

    public function form(Schema $form): Schema
    {
        return $form->schema([
            TextInput::make('name')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->heading('Cajeros Asignados')
            ->columns([
                TextColumn::make('name')->label('Usuario'),
                TextColumn::make('pivot.turno_id')
                    ->label('Turno')
                    ->formatStateUsing(function ($state) {
                        static $cache = null;
                        $cache ??= Turno::pluck('nombre', 'id');
                        return $cache[$state] ?? 'N/A';
                    })
                    ->badge()
                    ->color('warning'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->authorize(fn() => auth()->user()?->can('cajas.editar'))
                    ->schema(fn(AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('turno_id')
                            ->label('Turno')
                            ->native(false)
                            ->options(fn() => Turno::where('empresa_id', Filament::getTenant()->id)->pluck('nombre', 'id'))
                            ->default(fn() => Turno::where('empresa_id', Filament::getTenant()->id)->where('nombre', 'MAÑANA')->value('id'))
                            ->required()
                            ->createOptionForm([
                                TextInput::make('nombre')->required(),
                                // 🟢 Configuración para formato AM/PM
                                Grid::make([
                                    'default' => 1,
                                    'md' => 2,
                                    'lg' => 2,
                                ])->schema([
                                    TimePicker::make('hora_inicio')
                                        ->seconds(false)
                                        ->native(false)
                                        ->format('H:i:s')
                                        ->displayFormat('h:i A')
                                        ->required()
                                        ->helperText('Selecciona la hora. El sistema mostrará el formato AM/PM.'),

                                    TimePicker::make('hora_fin')
                                        ->seconds(false)
                                        ->native(false)
                                        ->format('H:i:s')
                                        ->displayFormat('h:i A')
                                        ->required()
                                        ->helperText('El sistema convierte automáticamente el formato 24h a AM/PM.'),
                                ])
                            ])
                            ->createOptionModalHeading('Nuevo Turno')
                            ->createOptionUsing(function (array $data): int {
                                return Turno::create([
                                    'nombre'      => $data['nombre'],
                                    'hora_inicio' => $data['hora_inicio'],
                                    'hora_fin'    => $data['hora_fin'],
                                    'empresa_id'  => Filament::getTenant()->id,
                                ])->id;
                            }),
                    ])
                    ->preloadRecordSelect(),
            ])
            ->actions([
                DetachAction::make()
                    ->authorize(fn() => auth()->user()?->can('cajas.editar')),
            ]);
    }
}

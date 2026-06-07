<?php

namespace App\Filament\Resources\Plans\Tables;

use App\Enums\EstadoGeneral;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre del Plan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('precio')
                    ->label('Precio')
                    ->money('PEN') // Formatea automáticamente con el símbolo S/
                    ->sortable(),

                TextColumn::make('ciclo_facturacion')
                    ->label('Ciclo')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'anual' => 'success', // Verde para anual
                        'mensual' => 'info',  // Azul para mensual
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst($state))
                    ->sortable(),

                TextColumn::make('maximo_usuarios')
                    ->label('Max. Usuarios')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('maximo_locales')
                    ->label('Max. Locales')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                IconColumn::make('tiene_variantes')
                    ->label('Variantes')
                    ->boolean() // Muestra un check verde o una X roja
                    ->alignCenter(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(EstadoGeneral $state): string => $state->getColor()),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->label('Filtrar por Estado')
                    ->options(EstadoGeneral::class),

                SelectFilter::make('ciclo_facturacion')
                    ->label('Filtrar por Ciclo')
                    ->options([
                        'mensual' => 'Mensual',
                        'anual' => 'Anual',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, $record) {
                        if ($record->suscripciones()->exists()) {
                            Notification::make()
                                ->warning()
                                ->title('No se puede eliminar')
                                ->body('Este plan está siendo usado por otros registros (suscripciones).')
                                ->send();
                            $action->halt();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, Collection $records) {
                            $planesEnUso = $records->filter(fn($plan) => $plan->suscripciones()->exists());
                            if ($planesEnUso->isNotEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Acción cancelada')
                                    ->body('Uno o más planes seleccionados están siendo usados por otros registros y no pueden ser eliminados.')
                                    ->send();
                                $action->halt();
                            }
                        }),
                ]),
            ])
            ->defaultSort('precio', 'asc');
    }
}

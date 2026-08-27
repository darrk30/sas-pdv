<?php

namespace App\Filament\Pdv\Resources\Gastos\Tables;

use App\Enums\CategoriaGasto;
use App\Enums\EstadoGasto;
use App\Models\Gasto;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GastosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->getStateUsing(fn ($record) => $record->serie . '-' . str_pad((string) $record->correlativo, 6, '0', STR_PAD_LEFT))
                    ->searchable(query: fn ($query, $search) => $query->whereRaw("CONCAT(serie, '-', LPAD(correlativo, 6, '0')) LIKE ?", ["%{$search}%"]))
                    ->sortable(['correlativo'])
                    ->copyable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('categoria')
                    ->label('Categoría')
                    ->badge()
                    ->sortable(),

                TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->descripcion),

                TextColumn::make('empleado.name')
                    ->label('Empleado')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('monto')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),

                TextColumn::make('registradoPor.name')
                    ->label('Registrado por')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('categoria')
                    ->label('Categoría')
                    ->options(CategoriaGasto::class)
                    ->native(false),

                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(EstadoGasto::class)
                    ->native(false),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()->label('Editar'),

                    Action::make('aprobar')
                        ->label('Aprobar')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('¿Aprobar este gasto?')
                        ->modalSubmitActionLabel('Sí, aprobar')
                        ->visible(fn (Gasto $record) => $record->estado === EstadoGasto::Pendiente
                            && (auth()->user()?->can('gastos.crear') ?? false))
                        ->action(function (Gasto $record) {
                            $record->update(['estado' => EstadoGasto::Aprobado]);
                            Notification::make()->title('Gasto aprobado')->success()->send();
                        }),

                    Action::make('anular')
                        ->label('Anular')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('¿Anular este gasto?')
                        ->modalDescription('Esta acción no se puede deshacer.')
                        ->modalSubmitActionLabel('Sí, anular')
                        ->visible(fn (Gasto $record) => ! $record->estaAnulado()
                            && (auth()->user()?->can('gastos.anular') ?? false))
                        ->action(function (Gasto $record) {
                            $record->update(['estado' => EstadoGasto::Anulado]);
                            Notification::make()->title('Gasto anulado')->success()->send();
                        }),
                ]),
            ])
            ->defaultSort('fecha', 'desc')
            ->striped();
    }
}

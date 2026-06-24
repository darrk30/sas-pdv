<?php

namespace App\Filament\Pdv\Resources\IngresoEgresos\Tables;

use App\Enums\CategoriaEgreso;
use App\Enums\EstadoMovimiento;
use App\Enums\TipoMovimiento;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class IngresoEgresosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                if (! self::esAdmin()) {
                    $query->where('user_id', auth()->id());
                }
            })
            ->columns([
                TextColumn::make('fecha_hora')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('sesionCaja.caja.nombre')
                    ->label('Caja')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                TextColumn::make('categoria')
                    ->label('Categoría')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('entregado_a')
                    ->label('Persona')
                    ->placeholder('—')
                    ->formatStateUsing(fn($state, $record) => $state
                        ?? $record->receptor?->name
                        ?? '—')
                    ->searchable(),

                TextColumn::make('monto')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable()
                    ->color(fn($record) => $record->tipo === TipoMovimiento::Ingreso ? 'success' : 'danger'),

                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->limit(35)
                    ->tooltip(fn($state) => $state)
                    ->toggleable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),

                TextColumn::make('registradoPor.name')
                    ->label('Registrado por')
                    ->toggleable(isToggledHiddenByDefault: ! self::esAdmin()),
            ])
            ->defaultSort('fecha_hora', 'desc')
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(TipoMovimiento::class),

                SelectFilter::make('categoria')
                    ->label('Categoría')
                    ->options(CategoriaEgreso::class),

                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(EstadoMovimiento::class),
            ])
            ->recordActions([
                Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('¿Anular este movimiento?')
                    ->modalDescription('Esta acción no se puede deshacer.')
                    ->visible(fn($record) => $record->estado === EstadoMovimiento::Aprobado)
                    ->action(function ($record) {
                        $record->update(['estado' => EstadoMovimiento::Anulado->value]);
                        Notification::make()
                            ->success()
                            ->title('Movimiento anulado.')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([10, 25, 50]);
    }

    private static function esAdmin(): bool
    {
        $user      = auth()->user();
        $empresaId = Filament::getTenant()?->id;

        if (! $user || ! $empresaId) {
            return false;
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.empresa_id', $empresaId)
            ->where('roles.name', 'Administrador')
            ->exists();
    }
}

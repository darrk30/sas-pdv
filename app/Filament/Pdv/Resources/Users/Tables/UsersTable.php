<?php

namespace App\Filament\Pdv\Resources\Users\Tables;

use App\Enums\EstadoGeneral;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder; // OJO: Asegúrate de que sea Eloquent, no Query\Builder
use Illuminate\Support\Facades\DB;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        // 1. OPTIMIZACIÓN: Le decimos a Spatie la sucursal ANTES de renderizar la tabla
        $empresaActual = Filament::getTenant();
        if ($empresaActual) {
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($empresaActual->id);
        }

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre Completo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable()
                    ->copyable()
                    ->tooltip('Clic para copiar'),

                // 2. OPTIMIZACIÓN: Al usar 'roles.name', Filament carga todos los roles
                // en 1 sola consulta (Eager Loading). Spatie ya lo filtra por la sucursal.
                TextColumn::make('roles.name')
                    ->label('Rol Asignado')
                    ->badge()
                    ->color('info')
                    ->searchable(query: function (Builder $query, string $search) use ($empresaActual) {
                        $query->whereHas('roles', function ($q) use ($search, $empresaActual) {
                            $q->where('roles.empresa_id', $empresaActual?->id)
                              ->where('roles.name', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('estado_pivot')
                    ->label('Estado')
                    ->badge()
                    ->getStateUsing(function ($record) use ($empresaActual) {
                        if (!$empresaActual) return null;
                        
                        $valorPivot = DB::table('empresa_user')
                            ->where('user_id', $record->id)
                            ->where('empresa_id', $empresaActual->id)
                            ->value('estado');
                            
                        return EstadoGeneral::tryFrom($valorPivot ?? 'activo');
                    })
                    ->color(fn(?EstadoGeneral $state): string => $state ? $state->getColor() : 'gray')
                    ->formatStateUsing(fn(?EstadoGeneral $state): string => $state ? $state->getLabel() : 'Desconocido'),

                TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(EstadoGeneral::class),

                SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship(
                        name: 'roles', 
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('roles.empresa_id', $empresaActual?->id)
                    )
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

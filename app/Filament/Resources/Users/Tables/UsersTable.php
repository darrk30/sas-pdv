<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\EstadoGeneral;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable()
                    ->sortable()
                    ->color('gray'),

                // Roles admin: query directo para evitar el team-scope de Spatie
                TextColumn::make('roles_admin')
                    ->label('Roles Admin')
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(fn(User $record) =>
                        DB::table('model_has_roles')
                            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                            ->where('model_has_roles.model_id', $record->id)
                            ->where('model_has_roles.model_type', User::class)
                            ->whereNull('roles.empresa_id')
                            ->pluck('roles.name')
                            ->toArray()
                    )
                    ->placeholder('—'),

                // Roles PDV: query directo, muestra "Empresa · Rol"
                TextColumn::make('roles_pdv')
                    ->label('Empresa / Rol PDV')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn(User $record) =>
                        DB::table('model_has_roles')
                            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                            ->join('empresas', 'empresas.id', '=', 'roles.empresa_id')
                            ->where('model_has_roles.model_id', $record->id)
                            ->where('model_has_roles.model_type', User::class)
                            ->whereNotNull('roles.empresa_id')
                            ->select('roles.name as rol', 'empresas.name as empresa')
                            ->get()
                            ->map(fn($r) => $r->empresa . ' · ' . $r->rol)
                            ->toArray()
                    )
                    ->placeholder('—'),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(EstadoGeneral $state): string => $state->getColor()),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}

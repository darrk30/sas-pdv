<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Models\Permission;
use App\Models\Role;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\PermissionRegistrar;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        // Permisos de scope 'admin' agrupados por módulo
        $permisosPorModulo = Permission::where('scope', 'admin')
            ->orderBy('module_label')
            ->orderBy('description')
            ->get()
            ->groupBy('module_label');

        $permisosSchema = [];

        if ($permisosPorModulo->isEmpty()) {
            $permisosSchema[] = Section::make('Permisos del sistema')
                ->description('No hay permisos de administración configurados.')
                ->icon('heroicon-o-information-circle')
                ->schema([]);
        } else {
            foreach ($permisosPorModulo as $moduloLabel => $permisos) {
                $opciones = $permisos->mapWithKeys(
                    fn(Permission $p) => [$p->id => ($p->description ?: $p->name)]
                )->toArray();

                $permisosSchema[] = Section::make($moduloLabel ?: 'General')
                    ->icon('heroicon-o-key')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        CheckboxList::make("permisos_modulo_{$permisos->first()->module}")
                            ->label('')
                            ->options($opciones)
                            ->columns(2)
                            ->gridDirection('row')
                            ->bulkToggleable()
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $state, ?Role $record) use ($permisos) {
                                if (! $record?->exists) return;
                                app(PermissionRegistrar::class)->setPermissionsTeamId(null);
                                $component->state(
                                    $record->permissions()
                                        ->whereIn('permissions.id', $permisos->pluck('id'))
                                        ->pluck('permissions.id')
                                        ->map(fn($id) => (string) $id)
                                        ->toArray()
                                );
                            }),
                    ]);
            }
        }

        return $schema->components([
            Section::make('Información del Rol')
                ->columnSpanFull()
                ->description('Define el nombre de este rol del panel de administración')
                ->icon('heroicon-o-shield-check')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre del rol')
                        ->placeholder('Ej. Soporte, Auditor...')
                        ->required()
                        ->maxLength(100)
                        ->unique(
                            table: 'roles',
                            column: 'name',
                            ignorable: fn($record) => $record,
                            modifyRuleUsing: fn($rule) => $rule->whereNull('empresa_id')
                        )
                        ->columnSpanFull(),
                ]),

            Grid::make(2)
                ->columnSpanFull()
                ->schema($permisosSchema),
        ]);
    }
}

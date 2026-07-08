<?php

namespace App\Filament\Pdv\Resources\Roles\Schemas;

use App\Models\Permission;
use App\Models\Role;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Facades\Filament;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $empresaId = Filament::getTenant()?->id;

        // Permisos agrupados por módulo — excluir módulos exclusivos del super-admin
        $permisosPorModulo = Permission::where('module', 'not like', 'admin_%')
            ->orderBy('module_label')
            ->orderBy('description')
            ->get()
            ->groupBy('module_label');

        $permisosSchema = [];

        if ($permisosPorModulo->isEmpty()) {
            $permisosSchema[] = Section::make('Permisos del sistema')
                ->description('Aún no hay permisos configurados en el sistema. Se agregarán próximamente.')
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
                    ->schema([
                        CheckboxList::make("permisos_modulo_{$permisos->first()->module}")
                            ->label('')
                            ->options($opciones)
                            ->columns(2)
                            ->gridDirection('row')
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $state, ?Role $record) use ($permisos, $empresaId) {
                                if (! $record?->exists) return;
                                app(\Spatie\Permission\PermissionRegistrar::class)
                                    ->setPermissionsTeamId($empresaId);
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
                ->description('Define el nombre con el que se identificará este rol en el sistema')
                ->icon('heroicon-o-shield-check')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre del rol')
                        ->placeholder('Ej. Supervisor, Encargado de almacén...')
                        ->required()
                        ->maxLength(100)
                        ->unique(
                            table: 'roles',
                            column: 'name',
                            ignorable: fn($record) => $record,
                            modifyRuleUsing: fn($rule) => $rule->where('empresa_id', Filament::getTenant()?->id)
                        )
                        ->columnSpanFull(),
                ]),

            Section::make('Permisos asignados')
                ->description('Selecciona qué acciones puede realizar este rol dentro del sistema')
                ->icon('heroicon-o-lock-open')
                ->schema($permisosSchema)
                ->collapsible(false),
        ]);
    }
}

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
        $empresa        = Filament::getTenant();
        $empresaId      = $empresa?->id;
        $plan           = $empresa?->planActual();
        $tieneTienda    = ($plan === null || $plan->tiene_catalogo_web) && ($empresa?->tieneModulo('pedidos_web') ?? true);
        $tieneVariantes = $plan === null || $plan->tiene_variantes;
        $tieneFE        = $empresa?->tieneFacturacionElectronica() ?? false;

        // Módulos completos a excluir según modulos_activos de la empresa
        $excludeModulos = [];
        if (! $tieneFE)                                           $excludeModulos[] = 'fe';
        if (! $tieneTienda)                                       $excludeModulos[] = 'tienda';
        if (! ($empresa?->tieneModulo('compras')   ?? true))     $excludeModulos[] = 'compras';
        if (! ($empresa?->tieneModulo('gastos')    ?? true))     $excludeModulos[] = 'gastos';
        if (! ($empresa?->tieneModulo('catalogo')  ?? true))     $excludeModulos[] = 'catalogo';
        if (! ($empresa?->tieneModulo('reportes')  ?? true))     $excludeModulos[] = 'reportes';
        if (! ($empresa?->tieneModulo('inventario') ?? true))    $excludeModulos[] = 'productos';

        // Permisos individuales a excluir por sub-módulo inactivo dentro de 'config'
        $excludePermisos = [];
        $configMap = [
            'metodos_envio'     => ['metodos_envio.ver', 'metodos_envio.crear', 'metodos_envio.editar', 'metodos_envio.eliminar'],
            'metodos_pago'      => ['metodos_pago.ver',  'metodos_pago.crear',  'metodos_pago.editar',  'metodos_pago.eliminar'],
            'cajas_registradoras'=> ['cajas.ver',        'cajas.crear',         'cajas.editar',         'cajas.eliminar'],
            'series'            => ['series.ver',        'series.crear',        'series.editar',        'series.eliminar'],
            'impresoras'        => ['impresoras.ver',    'impresoras.crear',    'impresoras.editar',    'impresoras.eliminar'],
        ];
        foreach ($configMap as $modulo => $permisos) {
            if (! ($empresa?->tieneModulo($modulo) ?? true)) {
                $excludePermisos = array_merge($excludePermisos, $permisos);
            }
        }

        // Permisos agrupados por módulo — excluir módulos inactivos y admin
        $permisosPorModulo = Permission::where('module', 'not like', 'admin_%')
            ->when(! empty($excludeModulos),  fn ($q) => $q->whereNotIn('module', $excludeModulos))
            ->when(! empty($excludePermisos), fn ($q) => $q->whereNotIn('name', $excludePermisos))
            ->when(! $tieneVariantes, fn ($q) => $q->whereNotIn('name', [
                'atributos.ver', 'atributos.crear', 'atributos.editar', 'atributos.eliminar',
            ]))
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
                            ->afterStateHydrated(function ($component, $state, ?Role $record) use ($permisos) {
                                if (! $record?->exists) return;
                                $empresaId = Filament::getTenant()?->id;
                                if (! $empresaId) return;
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

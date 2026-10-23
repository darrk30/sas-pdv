<?php

namespace App\Filament\Pdv\Resources\Roles\Schemas;

use App\Models\Permission;
use App\Models\Role;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Facades\Filament;
use Illuminate\Support\Str;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $empresa        = Filament::getTenant();
        $empresaId      = $empresa?->id;
        $tieneTienda        = ($empresa?->tieneFeature('catalogo_web') ?? false) && ($empresa?->tieneModulo('pedidos_web') ?? true);
        $tieneCupones       = $empresa?->tieneModulo('cupones') ?? false;
        $tieneVariantes     = $empresa?->tieneFeature('variantes') ?? false;
        $tieneFE            = $empresa?->tieneFacturacionElectronica() ?? false;
        $tieneListaPrecios  = $empresa?->tieneFeature('lista_precios') ?? false;
        $tieneRecetas       = $empresa?->tieneModulo('recetas') ?? false;

        // Módulos completos a excluir según modulos_activos de la empresa
        $excludeModulos = [];
        if (! $tieneFE)                                               $excludeModulos[] = 'fe';
        if (! $tieneTienda)                                           $excludeModulos[] = 'tienda';
        if (! ($empresa?->tieneModulo('compras')     ?? true))       $excludeModulos[] = 'compras';
        if (! ($empresa?->tieneModulo('gastos')      ?? true))       $excludeModulos[] = 'gastos';
        if (! ($empresa?->tieneModulo('catalogo')    ?? true))       $excludeModulos[] = 'catalogo';
        if (! ($empresa?->tieneModulo('reportes')    ?? true))       $excludeModulos[] = 'reportes';
        if (! ($empresa?->tieneModulo('inventario')  ?? true))       $excludeModulos[] = 'productos';
        if (! ($empresa?->tieneModulo('restaurante') ?? false)) {
            $excludeModulos[] = 'restaurante';
            $excludeModulos[] = 'pisos_mesas';
        }
        if (! ($empresa?->tieneFeature('cuentas'))) {
            $excludeModulos[] = 'cuentas';
            $excludeModulos[] = 'cuentas_cobrar';
            $excludeModulos[] = 'cuentas_pagar';
        }
        if (! $tieneRecetas)                                              $excludeModulos[] = 'recetas';

        // Permisos individuales a excluir por sub-módulo inactivo dentro de 'config'
        $excludePermisos = [];
        $configMap = [
            'metodos_envio'     => ['metodos_envio.ver', 'metodos_envio.crear', 'metodos_envio.editar', 'metodos_envio.eliminar'],
            'metodos_pago'      => ['metodos_pago.ver',  'metodos_pago.crear',  'metodos_pago.editar',  'metodos_pago.eliminar'],
            'cajas_registradoras' => ['cajas.ver',        'cajas.crear',         'cajas.editar',         'cajas.eliminar'],
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
            ->when(! empty($excludeModulos),  fn($q) => $q->whereNotIn('module', $excludeModulos))
            ->when(! empty($excludePermisos), fn($q) => $q->whereNotIn('name', $excludePermisos))
            ->when(! $tieneVariantes, fn($q) => $q->whereNotIn('name', [
                'atributos.ver',
                'atributos.crear',
                'atributos.editar',
                'atributos.eliminar',
            ]))
            ->when(! $tieneListaPrecios, fn($q) => $q->whereNotIn('name', [
                'listas_precios.ver',
                'listas_precios.crear',
                'listas_precios.editar',
                'listas_precios.eliminar',
            ]))
            ->when(! $tieneCupones, fn($q) => $q->whereNotIn('name', [
                'cupones.ver',
                'cupones.crear',
                'cupones.editar',
                'cupones.eliminar',
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
                $modulo        = $permisos->first()->module;
                $porGrupo      = $permisos->groupBy(fn($p) => $p->group ?? '');
                $tieneSubgrupos = $porGrupo->count() > 1;

                $buildCheckbox = function ($subPermisos, string $fieldKey) {
                    $opciones = $subPermisos->mapWithKeys(
                        fn(Permission $p) => [$p->id => ($p->description ?: $p->name)]
                    )->toArray();

                    return CheckboxList::make($fieldKey)
                        ->label('')
                        ->options($opciones)
                        ->columns(['default' => 1, 'sm' => 2, 'md' => 3, 'lg' => 4])
                        ->gridDirection('row')
                        ->columnSpanFull()
                        ->bulkToggleable()
                        ->dehydrated(false)
                        ->afterStateHydrated(function ($component, ?Role $record) use ($subPermisos) {
                            if (! $record?->exists) return;
                            app(\Spatie\Permission\PermissionRegistrar::class)
                                ->setPermissionsTeamId(Filament::getTenant()?->id);
                            $component->state(
                                $record->permissions()
                                    ->whereIn('permissions.id', $subPermisos->pluck('id'))
                                    ->pluck('permissions.id')
                                    ->map(fn($id) => (string) $id)
                                    ->toArray()
                            );
                        });
                };

                if ($tieneSubgrupos) {
                    $subSchemas = [];
                    foreach ($porGrupo as $grupo => $subPermisos) {
                        $slug     = $grupo ? Str::slug($grupo, '_') : 'general';
                        $fieldKey = "permisos_modulo_{$modulo}_{$slug}";
                        $checkbox = $buildCheckbox($subPermisos, $fieldKey);

                        if ($grupo) {
                            $subSchemas[] = Fieldset::make($grupo)->schema([$checkbox]);
                        } else {
                            $subSchemas[] = $checkbox->label('')->columnSpanFull();
                        }
                    }
                    $permisosSchema[] = Section::make($moduloLabel ?: 'General')
                        ->icon('heroicon-o-key')
                        ->collapsible()
                        ->collapsed()
                        ->schema($subSchemas);
                } else {
                    $fieldKey = "permisos_modulo_{$modulo}";
                    $permisosSchema[] = Section::make($moduloLabel ?: 'General')
                        ->icon('heroicon-o-key')
                        ->collapsible()
                        ->collapsed()
                        ->schema([$buildCheckbox($permisos, $fieldKey)]);
                }
            }
        }

        return $schema->components([
            Section::make('Información del Rol')
                ->columnSpanFull()
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

            Grid::make(1)
                ->columnSpanFull()
                ->schema($permisosSchema),
        ]);
    }
}

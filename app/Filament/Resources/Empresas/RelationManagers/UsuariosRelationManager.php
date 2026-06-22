<?php

namespace App\Filament\Resources\Empresas\RelationManagers;

use App\Filament\Resources\Empresas\EmpresaResource;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class UsuariosRelationManager extends RelationManager
{
    protected static string $relationship = 'usuarios';
    protected static ?string $title = 'Usuarios';

    protected static ?string $relatedResource = EmpresaResource::class;

    public function table(Table $table): Table
    {
        // 🟢 Aseguramos el contexto de Spatie para la empresa actual
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->getOwnerRecord()->id);

        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),

                TextColumn::make('roles.name')
                    ->label('Roles Asignados')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(function (User $record) {
                        return $record->roles()->pluck('name');
                    }),
            ])
            ->filters([])
            ->headerActions([
                // 🟢 BOTÓN: NUEVO USUARIO
                CreateAction::make()
                    ->label('Nuevo Usuario')
                    ->iconButton()
                    ->hiddenLabel()
                    ->tooltip('Crear Nuevo Usuario')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary'),

                // 🟢 BOTÓN: VINCULAR EXISTENTE
                AttachAction::make()
                    ->label('Vincular Existente')
                    ->iconButton()
                    ->hiddenLabel()
                    ->tooltip('Vincular Usuario Existente')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->modalHeading('Vincular Usuario a la Empresa')
                    ->attachAnother(false)
                    ->preloadRecordSelect()
                    ->recordTitle(fn(User $record): string => $record->name . ' — ' . $record->email) // ← le dice qué mostrar
                    ->recordSelectSearchColumns(['name', 'email']) // ← busca por nombre y email
                    ->schema(fn(AttachAction $action): array => [
                        $action->getRecordSelect(),

                        Select::make('role')
                            ->label('Asignar Rol Inicial')
                            ->options(fn() => Role::where('empresa_id', $this->getOwnerRecord()->id)->pluck('name', 'name'))
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->after(function (User $record, array $data) {
                        app(PermissionRegistrar::class)->setPermissionsTeamId($this->getOwnerRecord()->id);
                        $record->assignRole($data['role']);
                    }),
            ])
            ->recordActions([
                // 🟢 1. BOTÓN: VER ACCESOS (SOLO LECTURA)
                Action::make('viewPermissions')
                    ->label('Ver Accesos')
                    ->iconButton()
                    ->hiddenLabel()
                    ->tooltip('Ver Accesos')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->modalHeading(fn(User $record) => 'Accesos de ' . $record->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->infolist(function (User $record) {
                        app(PermissionRegistrar::class)->setPermissionsTeamId($this->getOwnerRecord()->id);

                        $roles = $record->roles()->pluck('name');

                        // NOTA: Ajusta 'module_label' según cómo tengas estructurada tu tabla de permisos
                        // Si no usas módulos, puedes agrupar por otra columna o simplemente no agrupar.
                        $permisosAgrupados = $record->getAllPermissions()->groupBy('module_label');

                        $seccionesDePermisos = [];

                        foreach ($permisosAgrupados as $modulo => $permisos) {
                            $seccionesDePermisos[] = TextEntry::make('modulo_' . $modulo)
                                ->label($modulo ?: 'Permisos Generales')
                                ->badge()
                                ->color('success')
                                ->getStateUsing(fn() => $permisos->pluck('name')->toArray()); // Uso 'name' o 'description' según tengas
                        }

                        if (empty($seccionesDePermisos)) {
                            $seccionesDePermisos[] = TextEntry::make('sin_acceso')
                                ->label('')
                                ->getStateUsing(fn() => 'Este usuario no tiene ningún permiso asignado en esta empresa.')
                                ->color('danger');
                        }

                        return [
                            Section::make('Cargos Actuales')
                                ->schema([
                                    TextEntry::make('roles_asignados')
                                        ->label('')
                                        ->badge()
                                        ->color('warning')
                                        ->getStateUsing(fn() => $roles->isEmpty() ? ['Ningún rol'] : $roles->toArray()),
                                ]),

                            Section::make('Permisos Efectivos Totales')
                                ->schema([
                                    Grid::make(2)->schema($seccionesDePermisos)
                                ])
                        ];
                    }),

                // 🟢 2. BOTÓN MAESTRO: ADMINISTRAR ROLES Y PERMISOS INDIVIDUALES
                Action::make('manageAccess')
                    ->label('Administrar rol')
                    ->iconButton()
                    ->hiddenLabel()
                    ->tooltip('Administrar rol')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->modalHeading(fn(User $record) => 'Control de Accesos: ' . $record->name)
                    ->modalWidth('4xl')
                    ->form(function () {
                        $empresaId = $this->getOwnerRecord()->id;

                        // Obtenemos todos los permisos (Ajusta la consulta si tienes un 'scope' o si quieres todos)
                        $modulos = \Spatie\Permission\Models\Permission::orderBy('name')->get()->groupBy('module_label');
                        $todosLosRoles = \Spatie\Permission\Models\Role::with('permissions')->where('empresa_id', $empresaId)->get();

                        $seccionesModulos = [];

                        foreach ($modulos as $moduleLabel => $permisosDelModulo) {
                            $nombreSeccion = $moduleLabel ?: 'Generales';
                            $nombreCampo = 'permissions_' . \Illuminate\Support\Str::slug($nombreSeccion);

                            $seccionesModulos[] = Section::make($nombreSeccion)
                                ->schema([
                                    CheckboxList::make($nombreCampo)
                                        // Usa 'name' en lugar de 'description' si tu tabla no tiene description
                                        ->options($permisosDelModulo->pluck('name', 'name')->toArray())
                                        ->label('')
                                        ->columns(1)
                                        ->bulkToggleable()

                                        // 🟢 MAGIA 1: Bloqueamos (ponemos en gris) los permisos que ya vienen con el rol
                                        ->disableOptionWhen(function (string $value, Get $get) use ($todosLosRoles) {
                                            $roleNames = $get('roles') ?? [];
                                            if (empty($roleNames)) return false;
                                            $rolePermissions = $todosLosRoles->whereIn('name', $roleNames)->flatMap->permissions->pluck('name')->toArray();
                                            return in_array($value, $rolePermissions);
                                        })

                                        // 🟢 Mostramos el texto del candado en los que están bloqueados
                                        ->descriptions(function (Get $get) use ($todosLosRoles, $permisosDelModulo) {
                                            $roleNames = $get('roles') ?? [];
                                            if (empty($roleNames)) return [];

                                            $rolePermissions = $todosLosRoles->whereIn('name', $roleNames)->flatMap->permissions->pluck('name')->toArray();

                                            $descripciones = [];
                                            foreach ($permisosDelModulo as $permiso) {
                                                if (in_array($permiso->name, $rolePermissions)) {
                                                    $descripciones[$permiso->name] = '🔒 (Bloqueado)';
                                                }
                                            }
                                            return $descripciones;
                                        })
                                ])->collapsible()->collapsed()->columnSpan(1);
                        }

                        return [
                            Section::make('Cargos (Roles)')
                                ->schema([
                                    Select::make('roles')
                                        ->label('Roles Asignados')
                                        ->multiple()
                                        ->options($todosLosRoles->pluck('name', 'name'))
                                        ->searchable()
                                        ->preload()
                                        ->live()

                                        // 🟢 MAGIA 2: Al cambiar el Rol, marcamos al instante las casillas de abajo
                                        ->afterStateUpdated(function (Set $set, Get $get, $state) use ($todosLosRoles, $modulos) {
                                            $rolePermissions = $todosLosRoles->whereIn('name', $state ?? [])->flatMap->permissions->pluck('name')->toArray();

                                            foreach ($modulos as $moduleLabel => $permisosDelModulo) {
                                                $nombreCampo = 'permissions_' . \Illuminate\Support\Str::slug($moduleLabel ?: 'Generales');
                                                $currentChecked = $get($nombreCampo) ?? [];
                                                // Mezclamos los permisos extra que ya tenía marcados con los nuevos del rol
                                                $merged = array_unique(array_merge($currentChecked, $rolePermissions));
                                                $set($nombreCampo, collect($merged)->intersect($permisosDelModulo->pluck('name'))->values()->toArray());
                                            }
                                        })
                                ]),

                            Section::make('Permisos Extra a Medida')
                                ->description('Las casillas grises (🔒) ya vienen incluidas en su rol y no se pueden quitar. Marca las casillas libres para darle permisos extra.')
                                ->schema([
                                    Grid::make(2)->schema($seccionesModulos)
                                ])
                        ];
                    })
                    ->mountUsing(function (Schema $form, User $record) {
                        $empresaId = $this->getOwnerRecord()->id;
                        app(PermissionRegistrar::class)->setPermissionsTeamId($empresaId);

                        // Cargamos los roles
                        $data = [
                            'roles' => $record->roles()->pluck('name')->toArray(),
                        ];

                        // Cargamos TODOS los permisos para que inicien marcados
                        $modulos = \Spatie\Permission\Models\Permission::get()->groupBy('module_label');
                        $todosLosPermisos = $record->getAllPermissions()->pluck('name')->toArray();

                        foreach ($modulos as $moduleLabel => $permisosDelModulo) {
                            $nombreCampo = 'permissions_' . \Illuminate\Support\Str::slug($moduleLabel ?: 'Generales');
                            $data[$nombreCampo] = collect($todosLosPermisos)
                                ->intersect($permisosDelModulo->pluck('name'))
                                ->values()
                                ->toArray();
                        }

                        $form->fill($data);
                    })
                    ->action(function (User $record, array $data) {
                        $empresaId = $this->getOwnerRecord()->id;
                        app(PermissionRegistrar::class)->setPermissionsTeamId($empresaId);

                        // 1. Sincronizamos los roles
                        $record->syncRoles($data['roles'] ?? []);

                        // 2. Juntamos los permisos libres que marcaste 
                        $permisosMarcados = [];
                        foreach ($data as $key => $valores) {
                            if (str_starts_with($key, 'permissions_') && is_array($valores)) {
                                $permisosMarcados = array_merge($permisosMarcados, $valores);
                            }
                        }

                        // 3. Forzamos la limpieza en la base de datos
                        DB::table('model_has_permissions')
                            ->where('model_id', $record->id)
                            ->where('model_type', get_class($record))
                            ->where('empresa_id', $empresaId) // Asegúrate de usar la columna correcta ('empresa_id' o 'team_id')
                            ->delete();

                        // 4. Doble filtro de seguridad: Quitamos los permisos del rol por si acaso
                        $record->forgetCachedPermissions();
                        $permisosDelRol = $record->getPermissionsViaRoles()->pluck('name')->toArray();
                        $permisosExtra = array_diff($permisosMarcados, $permisosDelRol);

                        // 5. Guardamos en BD
                        if (!empty($permisosExtra)) {
                            $permisosObj = \Spatie\Permission\Models\Permission::whereIn('name', $permisosExtra)->get();

                            $insertData = [];
                            foreach ($permisosObj as $permiso) {
                                $insertData[] = [
                                    'permission_id' => $permiso->id,
                                    'model_type'    => get_class($record),
                                    'model_id'      => $record->id,
                                    'empresa_id'    => $empresaId, // <-- Modifica a 'team_id' si esa es la columna real en tu BD
                                ];
                            }

                            DB::table('model_has_permissions')->insert($insertData);
                        }

                        $record->forgetCachedPermissions();
                    }),

                // 🟢 3. BOTÓN: DESVINCULAR
                DetachAction::make()
                    ->label('Desvincular')
                    ->iconButton()
                    ->hiddenLabel()
                    ->tooltip('Desvincular empleado')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->modalHeading('Quitar empleado de la empresa')
                    ->before(function (User $record) {
                        $empresaId = $this->getOwnerRecord()->id;
                        app(PermissionRegistrar::class)->setPermissionsTeamId($empresaId);

                        // Le quitamos roles
                        $record->syncRoles([]);

                        // Le quitamos permisos extra
                        DB::table(config('permission.table_names.model_has_permissions'))
                            ->where('model_id', $record->id)
                            ->where('model_type', get_class($record))
                            ->where('empresa_id', $empresaId) // Ajustar si es team_id
                            ->delete();

                        $record->forgetCachedPermissions();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $empresaId = $this->getOwnerRecord()->id;
                            app(PermissionRegistrar::class)->setPermissionsTeamId($empresaId);

                            foreach ($records as $record) {
                                $record->syncRoles([]);
                                DB::table(config('permission.table_names.model_has_permissions'))
                                    ->where('model_id', $record->id)
                                    ->where('model_type', get_class($record))
                                    ->where('empresa_id', $empresaId) // Ajustar si es team_id
                                    ->delete();
                                $record->forgetCachedPermissions();
                            }
                        }),
                ]),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->label('Nombre Completo')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Correo Electrónico')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                TextInput::make('password')
                    ->label('Contraseña Temporal')
                    ->password()
                    ->required(fn(string $operation): bool => $operation === 'create')
                    ->dehydrated(fn(?string $state) => filled($state))
                    ->dehydrateStateUsing(fn(string $state) => Hash::make($state)),
            ]);
    }
}

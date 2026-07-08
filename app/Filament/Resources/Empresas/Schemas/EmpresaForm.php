<?php

namespace App\Filament\Resources\Empresas\Schemas;

use App\Enums\EstadoGeneral;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class EmpresaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Usamos Tabs para separar visualmente lo crítico de lo administrativo
                Tabs::make('Configuración Empresa')
                    ->tabs([
                        Tab::make('General')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('name')->label('Nombre de la Empresa')->required()->columnSpan(2),
                                    TextInput::make('ruc')->label('RUC')->required()->maxLength(11)->numeric(),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('email')->label('Email de Contacto')->email(),
                                    TextInput::make('telefono')->label('Teléfono')->tel(),
                                    TextInput::make('slug')->label('URL amigable (Slug)')->required()->unique(ignoreRecord: true),
                                    Select::make('estado')->options(['activo' => 'Activo', 'inactivo' => 'Inactivo'])->default('activo')->native(false),
                                ]),
                                FileUpload::make('logo')->label('Logotipo')->image()->directory('logos')->columnSpanFull(),
                            ]),

                        Tab::make('Ubicación')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                TextInput::make('direccion')->label('Dirección Completa')->columnSpanFull(),
                                Grid::make(3)->schema([
                                    TextInput::make('departamento'),
                                    TextInput::make('provincia'),
                                    TextInput::make('distrito'),
                                ]),
                                Grid::make(3)->schema([
                                    TextInput::make('ubigeo'),
                                    TextInput::make('country_code')->label('Cod. País')->default('PE'),
                                    TextInput::make('cod_local')->label('Cod. Local')->default('0000'),
                                ]),
                            ]),

                        Tab::make('Sistema')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('carta_activa_cliente')
                                        ->label('Carta Activa Cliente')
                                        ->options(['activo' => 'Activo', 'inactivo' => 'Inactivo'])
                                        ->native(false),
                                    Select::make('carta_activa_admin')
                                        ->label('Carta Activa Admin')
                                        ->options(['activo' => 'Activo', 'inactivo' => 'Inactivo'])
                                        ->native(false),
                                ]),
                            ]),
                        // Tab::make('Usuarios y Roles')
                        //     ->icon('heroicon-o-users')
                        //     ->schema([
                        //         Repeater::make('usuarios')
                        //             ->label('Usuarios Asignados')
                        //             ->schema([
                        //                 Grid::make(2)->schema([
                        //                     Select::make('user_id')
                        //                         ->label('Usuario')
                        //                         ->options(User::all()->pluck('name', 'id'))
                        //                         ->searchable()
                        //                         ->preload()
                        //                         ->required()
                        //                         ->createOptionForm([
                        //                             TextInput::make('name')->required(),
                        //                             TextInput::make('email')->email()->required(),
                        //                             TextInput::make('password')->password()->required(),
                        //                         ])
                        //                         ->createOptionUsing(function (array $data) {
                        //                             return User::create([
                        //                                 'name' => $data['name'],
                        //                                 'email' => $data['email'],
                        //                                 'password' => Hash::make($data['password']),
                        //                             ])->id;
                        //                         }),

                        //                     Select::make('roles')
                        //                         ->label('Rol')
                        //                         ->options(Role::all()->pluck('name', 'id'))
                        //                         ->multiple()
                        //                         ->preload()
                        //                         ->native(false),
                        //                 ]),
                        //             ])
                        //             ->itemLabel(fn(array $state): ?string => User::find($state['user_id'])?->name ?? 'Nuevo Usuario')
                        //             ->addActionLabel('Vincular usuario')
                        //             ->columnSpanFull()
                        //             ->afterStateHydrated(function ($component, $state, $record) {
                        //                 if ($record) {
                        //                     $data = $record->usuarios->map(fn($user) => [
                        //                         'user_id' => $user->id,
                        //                         'roles' => $user->roles->pluck('id')->toArray(),
                        //                     ])->toArray();
                        //                     $component->state($data);
                        //                 }
                        //             })
                        //     ]),

                        Tab::make('Módulos')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Grid::make(2)->schema([

                                    // ── CAJA ─────────────────────────────────────────
                                    Section::make('Caja')
                                        ->icon('heroicon-o-receipt-percent')
                                        ->description('Ventas en mostrador, sesiones y cierres de caja, ingresos y egresos')
                                        ->compact()
                                        ->columnSpan(1)
                                        ->schema([
                                            Toggle::make('modulos_activos.caja')
                                                ->label('Activar módulo completo')
                                                ->onColor('success')
                                                ->live()
                                                ->default(true)
                                                ->columnSpanFull(),
                                            Grid::make(2)
                                                ->schema([
                                                    Toggle::make('modulos_activos.punto_de_venta')->label('Punto de Venta')->default(true),
                                                    Toggle::make('modulos_activos.sesion_cajas')->label('Sesiones de Caja')->default(true),
                                                    Toggle::make('modulos_activos.cierres_caja')->label('Cierres de Caja')->default(true),
                                                    Toggle::make('modulos_activos.ingresos_egresos')->label('Ingresos y Egresos')->default(true),
                                                ])
                                                ->hidden(fn(Get $get) => ! (bool) $get('modulos_activos.caja'))
                                                ->columnSpanFull(),
                                        ]),

                                    // ── PRODUCTOS ────────────────────────────────────
                                    Section::make('Productos')
                                        ->icon('heroicon-o-cube')
                                        ->description('Catálogo, órdenes web, ajustes, despacho, promociones y kardex')
                                        ->compact()
                                        ->columnSpan(1)
                                        ->schema([
                                            Toggle::make('modulos_activos.productos')
                                                ->label('Activar módulo completo')
                                                ->onColor('success')
                                                ->live()
                                                ->default(true)
                                                ->columnSpanFull(),
                                            Grid::make(2)
                                                ->schema([
                                                    Toggle::make('modulos_activos.gestion_productos')->label('Gestión de Productos')->default(true),
                                                    Toggle::make('modulos_activos.ordenes_web')->label('Órdenes Web')->default(true),
                                                    Toggle::make('modulos_activos.ajustes_stock')->label('Ajustes de Stock')->default(true),
                                                    Toggle::make('modulos_activos.despacho')->label('Despacho')->default(true),
                                                    Toggle::make('modulos_activos.promociones')->label('Promociones')->default(true),
                                                    Toggle::make('modulos_activos.kardex')->label('Kardex')->default(true),
                                                ])
                                                ->hidden(fn(Get $get) => ! (bool) $get('modulos_activos.productos'))
                                                ->columnSpanFull(),
                                        ]),

                                    // ── COMPRAS ──────────────────────────────────────
                                    Section::make('Compras')
                                        ->icon('heroicon-o-shopping-cart')
                                        ->description('Registro de compras y gestión de proveedores')
                                        ->compact()
                                        ->columnSpan(1)
                                        ->schema([
                                            Toggle::make('modulos_activos.compras')
                                                ->label('Activar módulo completo')
                                                ->onColor('success')
                                                ->live()
                                                ->default(true)
                                                ->columnSpanFull(),
                                            Grid::make(2)
                                                ->schema([
                                                    Toggle::make('modulos_activos.gestion_compras')->label('Compras')->default(true),
                                                    Toggle::make('modulos_activos.proveedores')->label('Proveedores')->default(true),
                                                ])
                                                ->hidden(fn(Get $get) => ! (bool) $get('modulos_activos.compras'))
                                                ->columnSpanFull(),
                                        ]),

                                    // ── REPORTES ─────────────────────────────────────
                                    Section::make('Reportes')
                                        ->icon('heroicon-o-document-chart-bar')
                                        ->description('Reportes de ventas, productos, clientes y cuentas por cobrar')
                                        ->compact()
                                        ->columnSpan(1)
                                        ->schema([
                                            Toggle::make('modulos_activos.reportes')
                                                ->label('Activar módulo completo')
                                                ->onColor('success')
                                                ->live()
                                                ->default(true)
                                                ->columnSpanFull(),
                                            Grid::make(2)
                                                ->schema([
                                                    Toggle::make('modulos_activos.reporte_ventas')->label('Reporte de Ventas')->default(true),
                                                    Toggle::make('modulos_activos.reporte_productos')->label('Reporte de Productos')->default(true),
                                                    Toggle::make('modulos_activos.reporte_clientes')->label('Reporte de Clientes')->default(true),
                                                    Toggle::make('modulos_activos.cuentas_por_cobrar')->label('Cuentas por Cobrar')->default(true),
                                                ])
                                                ->hidden(fn(Get $get) => ! (bool) $get('modulos_activos.reportes'))
                                                ->columnSpanFull(),
                                        ]),

                                    // ── CATÁLOGO WEB ─────────────────────────────────
                                    Section::make('Catálogo Web')
                                        ->icon('heroicon-o-globe-alt')
                                        ->description('Tienda online con categorías, marcas, atributos y producción')
                                        ->compact()
                                        ->columnSpan(1)
                                        ->schema([
                                            Toggle::make('modulos_activos.catalogo')
                                                ->label('Activar módulo completo')
                                                ->onColor('success')
                                                ->live()
                                                ->default(true)
                                                ->columnSpanFull(),
                                            Grid::make(2)
                                                ->schema([
                                                    Toggle::make('modulos_activos.produccion')->label('Producción')->default(true),
                                                    Toggle::make('modulos_activos.categorias')->label('Categorías')->default(true),
                                                    Toggle::make('modulos_activos.marcas')->label('Marcas')->default(true),
                                                    Toggle::make('modulos_activos.atributos')->label('Atributos')->default(true),
                                                ])
                                                ->hidden(fn(Get $get) => ! (bool) $get('modulos_activos.catalogo'))
                                                ->columnSpanFull(),
                                        ]),

                                    // ── CONFIGURACIÓN ────────────────────────────────
                                    Section::make('Configuración')
                                        ->icon('heroicon-o-cog-6-tooth')
                                        ->description('Cajas, series, métodos de pago/envío, impresoras, usuarios y roles')
                                        ->compact()
                                        ->columnSpan(1)
                                        ->schema([
                                            Toggle::make('modulos_activos.configuracion')
                                                ->label('Activar módulo completo')
                                                ->onColor('success')
                                                ->live()
                                                ->default(true)
                                                ->columnSpanFull(),
                                            Grid::make(2)
                                                ->schema([
                                                    Toggle::make('modulos_activos.cajas_registradoras')->label('Cajas Registradoras')->default(true),
                                                    Toggle::make('modulos_activos.metodos_pago')->label('Métodos de Pago')->default(true),
                                                    Toggle::make('modulos_activos.metodos_envio')->label('Métodos de Envío')->default(true),
                                                    Toggle::make('modulos_activos.series')->label('Series')->default(true),
                                                    Toggle::make('modulos_activos.impresoras')->label('Impresoras')->default(true),
                                                    Toggle::make('modulos_activos.usuarios_roles')->label('Usuarios y Roles')->default(true),
                                                ])
                                                ->hidden(fn(Get $get) => ! (bool) $get('modulos_activos.configuracion'))
                                                ->columnSpanFull(),
                                        ]),

                                ]),
                            ]),

                        Tab::make('Suscripción y Plan')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                Fieldset::make('Detalles de la Suscripción')
                                    ->relationship('suscripcion')
                                    ->columns([
                                        'default' => 1, // Celulares pequeños (vertical)
                                        'sm' => 2,      // Celulares grandes (horizontal) y Tablets pequeñas
                                        'lg' => 3,      // Monitores y Laptops
                                    ])
                                    ->schema([
                                        Select::make('plan_id')
                                            ->label('Plan Contratado')
                                            ->relationship('plan', 'nombre')
                                            ->native(false)
                                            ->required()
                                            ->live()
                                            ->columnSpan([
                                                'default' => 1, // En celular pequeño, 1 columna
                                                'sm' => 2,      // En tablet/celular grande, 2 columnas
                                                'lg' => 2,      // En PC, 2 de las 3 columnas
                                            ])
                                            ->afterStateUpdated(function ($state, Set $set) {
                                                if (! $state) return;

                                                $plan = Plan::find($state);
                                                if ($plan) {
                                                    $set('precio_pagado', $plan->precio);
                                                    $set('fecha_inicio', now()->format('Y-m-d'));

                                                    $fechaFin = $plan->ciclo_facturacion === 'anual'
                                                        ? now()->addYear()->format('Y-m-d')
                                                        : now()->addMonth()->format('Y-m-d');

                                                    $set('fecha_fin', $fechaFin);
                                                }
                                            }),

                                        Select::make('estado')
                                            ->label('Estado')
                                            ->options(EstadoGeneral::class)
                                            ->default(EstadoGeneral::Activo)
                                            ->required()
                                            ->columnSpan(1), // Automáticamente tomará el espacio dictado por el Fieldset

                                        TextInput::make('precio_pagado')
                                            ->label('Precio Acordado')
                                            ->required()
                                            ->numeric()
                                            ->prefix('S/')
                                            ->columnSpan(1),

                                        DatePicker::make('fecha_inicio')
                                            ->label('Fecha de Inicio')
                                            ->required()
                                            ->native(false)
                                            ->default(now())
                                            ->columnSpan(1),

                                        DatePicker::make('fecha_fin')
                                            ->label('Fecha de Vencimiento')
                                            ->required()
                                            ->native(false)
                                            ->columnSpan(1),
                                    ])
                            ]),
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
            ]);
    }
}

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
    // Cuando un hijo cambia, recalcula si todos los hijos del padre están ON
    private static function syncPadre(string $padre, array $hijos, Get $get, Set $set): void
    {
        $todos = array_reduce(
            $hijos,
            fn($carry, $hijo) => $carry && (bool) $get("modulos_activos.$hijo"),
            true,
        );
        $set("modulos_activos.$padre", $todos);
    }

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

                                    // ── PUNTO DE VENTA ───────────────────────────────
                                    Section::make('Punto de Venta')
                                        ->icon('heroicon-o-receipt-percent')
                                        ->description('Ventas en mostrador, sesiones, cierres, ingresos y egresos')
                                        ->compact()->columnSpan(1)
                                        ->schema([
                                            Toggle::make('modulos_activos.caja')
                                                ->label('Activar módulo completo')->onColor('success')->live()->default(true)->columnSpanFull()
                                                ->afterStateUpdated(function (bool $state, Set $set) {
                                                    foreach (['punto_de_venta','sesion_cajas','ventas_turno','ingresos_egresos','cierres_caja'] as $s) {
                                                        $set("modulos_activos.$s", $state);
                                                    }
                                                }),
                                            Grid::make(2)->schema([
                                                Toggle::make('modulos_activos.punto_de_venta')->label('Punto de Venta')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('caja', ['punto_de_venta','sesion_cajas','ventas_turno','ingresos_egresos','cierres_caja'], $get, $set)),
                                                Toggle::make('modulos_activos.sesion_cajas')->label('Sesiones de Caja')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('caja', ['punto_de_venta','sesion_cajas','ventas_turno','ingresos_egresos','cierres_caja'], $get, $set)),
                                                Toggle::make('modulos_activos.ventas_turno')->label('Ventas del Turno')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('caja', ['punto_de_venta','sesion_cajas','ventas_turno','ingresos_egresos','cierres_caja'], $get, $set)),
                                                Toggle::make('modulos_activos.ingresos_egresos')->label('Ingresos y Egresos')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('caja', ['punto_de_venta','sesion_cajas','ventas_turno','ingresos_egresos','cierres_caja'], $get, $set)),
                                                Toggle::make('modulos_activos.cierres_caja')->label('Cierres de Caja')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('caja', ['punto_de_venta','sesion_cajas','ventas_turno','ingresos_egresos','cierres_caja'], $get, $set)),
                                            ])->columnSpanFull(),
                                        ]),

                                    // ── INVENTARIO ───────────────────────────────────
                                    Section::make('Inventario')
                                        ->icon('heroicon-o-cube')
                                        ->description('Gestión de productos, inventario, kardex y ajustes de stock')
                                        ->compact()->columnSpan(1)
                                        ->schema([
                                            Toggle::make('modulos_activos.inventario')
                                                ->label('Activar módulo completo')->onColor('success')->live()->default(true)->columnSpanFull()
                                                ->afterStateUpdated(function (bool $state, Set $set) {
                                                    foreach (['gestion_productos','gestion_inventario','kardex','ajustes_stock'] as $s) {
                                                        $set("modulos_activos.$s", $state);
                                                    }
                                                }),
                                            Grid::make(2)->schema([
                                                Toggle::make('modulos_activos.gestion_productos')->label('Productos')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('inventario', ['gestion_productos','gestion_inventario','kardex','ajustes_stock'], $get, $set)),
                                                Toggle::make('modulos_activos.gestion_inventario')->label('Inventario')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('inventario', ['gestion_productos','gestion_inventario','kardex','ajustes_stock'], $get, $set)),
                                                Toggle::make('modulos_activos.kardex')->label('Kardex')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('inventario', ['gestion_productos','gestion_inventario','kardex','ajustes_stock'], $get, $set)),
                                                Toggle::make('modulos_activos.ajustes_stock')->label('Ajustes de Stock')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('inventario', ['gestion_productos','gestion_inventario','kardex','ajustes_stock'], $get, $set)),
                                            ])->columnSpanFull(),
                                        ]),

                                    // ── PEDIDOS WEB ──────────────────────────────────
                                    Section::make('Pedidos Web')
                                        ->icon('heroicon-o-globe-alt')
                                        ->description('Órdenes, clientes web, promociones y despachos')
                                        ->compact()->columnSpan(1)
                                        ->schema([
                                            Toggle::make('modulos_activos.pedidos_web')
                                                ->label('Activar módulo completo')->onColor('success')->live()->default(true)->columnSpanFull()
                                                ->afterStateUpdated(function (bool $state, Set $set) {
                                                    foreach (['ordenes_web','clientes','promociones','despacho'] as $s) {
                                                        $set("modulos_activos.$s", $state);
                                                    }
                                                }),
                                            Grid::make(2)->schema([
                                                Toggle::make('modulos_activos.ordenes_web')->label('Órdenes')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('pedidos_web', ['ordenes_web','clientes','promociones','despacho'], $get, $set)),
                                                Toggle::make('modulos_activos.clientes')->label('Clientes')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('pedidos_web', ['ordenes_web','clientes','promociones','despacho'], $get, $set)),
                                                Toggle::make('modulos_activos.promociones')->label('Promociones')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('pedidos_web', ['ordenes_web','clientes','promociones','despacho'], $get, $set)),
                                                Toggle::make('modulos_activos.despacho')->label('Despachos')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('pedidos_web', ['ordenes_web','clientes','promociones','despacho'], $get, $set)),
                                            ])->columnSpanFull(),
                                        ]),

                                    // ── COMPRAS ──────────────────────────────────────
                                    Section::make('Compras')
                                        ->icon('heroicon-o-shopping-cart')
                                        ->description('Registro de compras y gestión de proveedores')
                                        ->compact()->columnSpan(1)
                                        ->schema([
                                            Toggle::make('modulos_activos.compras')
                                                ->label('Activar módulo completo')->onColor('success')->live()->default(true)->columnSpanFull()
                                                ->afterStateUpdated(function (bool $state, Set $set) {
                                                    foreach (['gestion_compras','proveedores'] as $s) {
                                                        $set("modulos_activos.$s", $state);
                                                    }
                                                }),
                                            Grid::make(2)->schema([
                                                Toggle::make('modulos_activos.gestion_compras')->label('Compras')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('compras', ['gestion_compras','proveedores'], $get, $set)),
                                                Toggle::make('modulos_activos.proveedores')->label('Proveedores')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('compras', ['gestion_compras','proveedores'], $get, $set)),
                                            ])->columnSpanFull(),
                                        ]),

                                    // ── CATÁLOGO ─────────────────────────────────────
                                    Section::make('Catálogo')
                                        ->icon('heroicon-o-tag')
                                        ->description('Categorías, marcas, atributos, producción y dimensiones')
                                        ->compact()->columnSpan(1)
                                        ->schema([
                                            Toggle::make('modulos_activos.catalogo')
                                                ->label('Activar módulo completo')->onColor('success')->live()->default(true)->columnSpanFull()
                                                ->afterStateUpdated(function (bool $state, Set $set) {
                                                    foreach (['categorias','marcas','atributos','produccion','dimensiones'] as $s) {
                                                        $set("modulos_activos.$s", $state);
                                                    }
                                                }),
                                            Grid::make(2)->schema([
                                                Toggle::make('modulos_activos.categorias')->label('Categorías')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('catalogo', ['categorias','marcas','atributos','produccion','dimensiones'], $get, $set)),
                                                Toggle::make('modulos_activos.marcas')->label('Marcas')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('catalogo', ['categorias','marcas','atributos','produccion','dimensiones'], $get, $set)),
                                                Toggle::make('modulos_activos.atributos')->label('Atributos')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('catalogo', ['categorias','marcas','atributos','produccion','dimensiones'], $get, $set)),
                                                Toggle::make('modulos_activos.produccion')->label('Producción')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('catalogo', ['categorias','marcas','atributos','produccion','dimensiones'], $get, $set)),
                                                Toggle::make('modulos_activos.dimensiones')->label('Dimensiones')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('catalogo', ['categorias','marcas','atributos','produccion','dimensiones'], $get, $set)),
                                            ])->columnSpanFull(),
                                        ]),

                                    // ── REPORTES ─────────────────────────────────────
                                    Section::make('Reportes')
                                        ->icon('heroicon-o-document-chart-bar')
                                        ->description('Todos los reportes del sistema')
                                        ->compact()->columnSpan(1)
                                        ->schema([
                                            Toggle::make('modulos_activos.reportes')
                                                ->label('Activar módulo completo')->onColor('success')->live()->default(true)->columnSpanFull()
                                                ->afterStateUpdated(function (bool $state, Set $set) {
                                                    foreach (['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes','cuentas_por_cobrar'] as $s) {
                                                        $set("modulos_activos.$s", $state);
                                                    }
                                                }),
                                            Grid::make(2)->schema([
                                                Toggle::make('modulos_activos.ventas_periodo')->label('Ventas por Período')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes','cuentas_por_cobrar'], $get, $set)),
                                                Toggle::make('modulos_activos.reporte_ventas')->label('Reporte de Ventas')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes','cuentas_por_cobrar'], $get, $set)),
                                                Toggle::make('modulos_activos.reporte_ganancias')->label('Reporte de Ganancias')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes','cuentas_por_cobrar'], $get, $set)),
                                                Toggle::make('modulos_activos.reporte_productos')->label('Productos más vendidos')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes','cuentas_por_cobrar'], $get, $set)),
                                                Toggle::make('modulos_activos.reporte_compras')->label('Reporte de Compras')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes','cuentas_por_cobrar'], $get, $set)),
                                                Toggle::make('modulos_activos.reporte_vendedores')->label('Vendedores')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes','cuentas_por_cobrar'], $get, $set)),
                                                Toggle::make('modulos_activos.reporte_ajustes')->label('Reporte de Ajustes')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes','cuentas_por_cobrar'], $get, $set)),
                                                Toggle::make('modulos_activos.reporte_clientes')->label('Reporte de Clientes')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes','cuentas_por_cobrar'], $get, $set)),
                                                Toggle::make('modulos_activos.cuentas_por_cobrar')->label('Cuentas por Cobrar')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes','cuentas_por_cobrar'], $get, $set)),
                                            ])->columnSpanFull(),
                                        ]),

                                    // ── CONFIGURACIÓN ────────────────────────────────
                                    Section::make('Configuración')
                                        ->icon('heroicon-o-cog-6-tooth')
                                        ->description('Cajas, series, métodos de pago/envío, impresoras, usuarios y roles')
                                        ->compact()->columnSpan(1)
                                        ->schema([
                                            Toggle::make('modulos_activos.configuracion')
                                                ->label('Activar módulo completo')->onColor('success')->live()->default(true)->columnSpanFull()
                                                ->afterStateUpdated(function (bool $state, Set $set) {
                                                    foreach (['cajas_registradoras','metodos_pago','metodos_envio','series','impresoras','usuarios_roles'] as $s) {
                                                        $set("modulos_activos.$s", $state);
                                                    }
                                                }),
                                            Grid::make(2)->schema([
                                                Toggle::make('modulos_activos.cajas_registradoras')->label('Cajas')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('configuracion', ['cajas_registradoras','metodos_pago','metodos_envio','series','impresoras','usuarios_roles'], $get, $set)),
                                                Toggle::make('modulos_activos.metodos_pago')->label('Métodos de Pago')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('configuracion', ['cajas_registradoras','metodos_pago','metodos_envio','series','impresoras','usuarios_roles'], $get, $set)),
                                                Toggle::make('modulos_activos.metodos_envio')->label('Métodos de Envío')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('configuracion', ['cajas_registradoras','metodos_pago','metodos_envio','series','impresoras','usuarios_roles'], $get, $set)),
                                                Toggle::make('modulos_activos.series')->label('Series')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('configuracion', ['cajas_registradoras','metodos_pago','metodos_envio','series','impresoras','usuarios_roles'], $get, $set)),
                                                Toggle::make('modulos_activos.impresoras')->label('Impresoras')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('configuracion', ['cajas_registradoras','metodos_pago','metodos_envio','series','impresoras','usuarios_roles'], $get, $set)),
                                                Toggle::make('modulos_activos.usuarios_roles')->label('Usuarios y Roles')->default(true)->live()
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('configuracion', ['cajas_registradoras','metodos_pago','metodos_envio','series','impresoras','usuarios_roles'], $get, $set)),
                                            ])->columnSpanFull(),
                                        ]),

                                ]),
                            ]),

                        Tab::make('Facturación Electrónica')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Configuración de Envío')
                                    ->description('Controla cuándo y cómo se emiten los comprobantes electrónicos')
                                    ->compact()
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Toggle::make('fe_envio_directo_boleta')
                                                ->label('Envío directo de Boletas')
                                                ->helperText('Si está desactivado, las boletas se acumularán en resumen diario')
                                                ->onColor('success'),
                                            Toggle::make('fe_envio_directo_factura')
                                                ->label('Envío directo de Facturas')
                                                ->helperText('Si está desactivado, las facturas quedan en estado "Por Enviar"')
                                                ->onColor('success'),
                                            Toggle::make('impresion_comprobante_directo')
                                                ->label('Impresión automática al emitir')
                                                ->onColor('success'),
                                            TextInput::make('igv_porcentaje')
                                                ->label('Porcentaje IGV (%)')
                                                ->numeric()
                                                ->default(18)
                                                ->suffix('%')
                                                ->minValue(0)
                                                ->maxValue(99),
                                        ]),
                                    ]),

                                Section::make('Credenciales SOL y Facturador')
                                    ->description('Datos de conexión al servidor de facturación FacturadorGreenter. Solo completar si el plan incluye FE.')
                                    ->compact()
                                    ->relationship('facturacion')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('sol_user')
                                                ->label('Usuario SOL')
                                                ->maxLength(20),
                                            TextInput::make('sol_pass')
                                                ->label('Clave SOL')
                                                ->password()
                                                ->revealable()
                                                ->dehydrated(fn($state) => filled($state)),
                                        ]),
                                        Grid::make(2)->schema([
                                            TextInput::make('facturador_url')
                                                ->label('URL del Facturador')
                                                ->url()
                                                ->placeholder('http://facturador.miempresa.com'),
                                            TextInput::make('facturador_api_token')
                                                ->label('Token API')
                                                ->password()
                                                ->revealable()
                                                ->dehydrated(fn($state) => filled($state)),
                                        ]),
                                        FileUpload::make('cert_path')
                                            ->label('Certificado digital (.pem)')
                                            ->helperText('Archivo .pem del certificado digital. Se guarda de forma privada en el servidor.')
                                            ->disk('local')
                                            ->directory('empresas/certs')
                                            ->visibility('private')
                                            ->acceptedFileTypes(['application/x-pem-file', 'application/octet-stream', 'text/plain'])
                                            ->maxSize(512)
                                            ->columnSpanFull(),
                                        Grid::make(2)->schema([
                                            TextInput::make('cert_password')
                                                ->label('Contraseña del certificado')
                                                ->password()
                                                ->revealable()
                                                ->helperText('Solo si el .pem tiene contraseña')
                                                ->dehydrated(fn($state) => filled($state)),
                                            Toggle::make('produccion')
                                                ->label('Entorno de Producción')
                                                ->helperText('Desactivado = Beta/homologación SUNAT')
                                                ->onColor('danger'),
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

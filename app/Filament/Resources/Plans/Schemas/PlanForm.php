<?php

namespace App\Filament\Resources\Plans\Schemas;

use App\Enums\EstadoGeneral;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PlanForm
{
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
                Section::make('Información del Plan')
                    ->description('Datos principales y configuración de cobro')
                    ->icon('heroicon-o-document-text')
                    ->columns([
                        'default' => 1, // 1 columna en celulares
                        'sm' => 2,      // 2 columnas en tablets
                        'lg' => 3,      // 3 columnas en pantallas grandes (PC)
                    ])
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre del Plan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan([
                                'default' => 1,
                                'sm' => 2,
                                'lg' => 2,
                            ]),

                        TextInput::make('subtitulo')
                            ->label('Subtítulo')
                            ->helperText('Frase corta que aparece bajo el nombre en la página pública.')
                            ->maxLength(120)
                            ->placeholder('Para negocios que empiezan a digitalizar sus ventas.')
                            ->columnSpanFull(),

                        Select::make('estado')
                            ->label('Estado')
                            ->options(EstadoGeneral::class)
                            ->default(EstadoGeneral::Activo)
                            ->required()
                            ->columnSpan(1), // Ocupa 1 columna siempre, adaptándose a su fila

                        TextInput::make('precio')
                            ->label('Precio mensual')
                            ->required()
                            ->numeric()
                            ->prefix('S/')
                            ->helperText('Precio por mes si se suscribe mensualmente.')
                            ->columnSpan(1),

                        TextInput::make('precio_anual')
                            ->label('Precio anual total')
                            ->numeric()
                            ->prefix('S/')
                            ->helperText('Precio total al pagar un año. Vacío = no ofrece plan anual.')
                            ->columnSpan(1),

                        TextInput::make('dias_prueba_gratuita')
                            ->label('Días de prueba gratuita')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('0 = sin prueba gratuita al registrarse.')
                            ->columnSpan(1),

                        RichEditor::make('descripcion')
                            ->label('Descripción / Características')
                            ->helperText('Puedes copiar y pegar texto con formato. Se mostrará tal cual en la página pública.')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'bulletList', 'orderedList',
                                'h2', 'h3',
                                'undo', 'redo',
                            ])
                            ->columnSpanFull(),
                    ])->columnSpanFull(),

                Section::make('Límites del Plan')
                    ->description('Cantidad máxima de recursos que puede tener la empresa')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->collapsible()
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                    ])
                    ->schema([
                        TextInput::make('maximo_usuarios')
                            ->label('Máximo de Usuarios')
                            ->helperText('Usuarios que pueden acceder al panel PDV')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->columnSpan(1),

                        TextInput::make('maximo_locales')
                            ->label('Máximo de Locales')
                            ->helperText('Sucursales o puntos de venta habilitados')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->columnSpan(1),
                    ])->columnSpanFull(),

                Section::make('Funcionalidades incluidas')
                    ->description('Módulos y características que se activan con este plan')
                    ->icon('heroicon-o-puzzle-piece')
                    ->collapsible()
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                    ])
                    ->schema([
                        Toggle::make('tiene_catalogo_web')
                            ->label('Catálogo Web / Tienda Online')
                            ->helperText('Permite activar la tienda online y recibir órdenes de clientes')
                            ->onColor('success')
                            ->default(false)
                            ->columnSpan(1),

                        Toggle::make('tiene_variantes')
                            ->label('Variantes de Productos')
                            ->helperText('Permite crear productos con tallas, colores u otras variantes')
                            ->onColor('success')
                            ->default(false)
                            ->columnSpan(1),

                        Toggle::make('facturacion_electronica')
                            ->label('Facturación Electrónica')
                            ->helperText('Permite emitir comprobantes electrónicos (boletas y facturas)')
                            ->onColor('success')
                            ->default(false)
                            ->columnSpan(1),

                        Toggle::make('tiene_impresion_directa')
                            ->label('Impresión Directa')
                            ->helperText('Permite enviar tickets automáticamente a la impresora sin diálogo del navegador')
                            ->onColor('success')
                            ->default(false)
                            ->columnSpan(1),

                        Toggle::make('tiene_lista_precios')
                            ->label('Listas de Precios')
                            ->helperText('Permite crear listas de precios diferenciadas por cliente o canal')
                            ->onColor('success')
                            ->default(false)
                            ->columnSpan(1),

                        Toggle::make('tiene_cuentas')
                            ->label('Cuentas por Cobrar / Pagar')
                            ->helperText('Permite gestionar créditos a clientes y deudas a proveedores')
                            ->onColor('success')
                            ->default(false)
                            ->columnSpan(1),
                    ])->columnSpanFull(),

                Section::make('Módulos del Plan')
                    ->description('Define qué módulos de navegación tendrá la empresa al suscribirse a este plan. Se copian automáticamente al crear la empresa y al cambiar de plan.')
                    ->icon('heroicon-o-squares-2x2')
                    ->collapsible()
                    ->schema([

                        // ── PUNTO DE VENTA ──────────────────────────────────────
                        Section::make('Punto de Venta')
                            ->icon('heroicon-o-receipt-percent')
                            ->compact()->collapsible()->collapsed()
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

                        // ── INVENTARIO ──────────────────────────────────────────
                        Section::make('Inventario')
                            ->icon('heroicon-o-cube')
                            ->compact()->collapsible()->collapsed()
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

                        // ── PEDIDOS WEB ─────────────────────────────────────────
                        Section::make('Pedidos Web / Catálogo')
                            ->icon('heroicon-o-globe-alt')
                            ->compact()->collapsible()->collapsed()
                            ->schema([
                                Toggle::make('modulos_activos.pedidos_web')
                                    ->label('Activar módulo completo')->onColor('success')->live()->default(false)->columnSpanFull()
                                    ->afterStateUpdated(function (bool $state, Set $set) {
                                        foreach (['ordenes_web','clientes','promociones','despacho'] as $s) {
                                            $set("modulos_activos.$s", $state);
                                        }
                                    }),
                                Grid::make(2)->schema([
                                    Toggle::make('modulos_activos.ordenes_web')->label('Órdenes')->default(false)->live()
                                        ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('pedidos_web', ['ordenes_web','clientes','promociones','despacho'], $get, $set)),
                                    Toggle::make('modulos_activos.clientes')->label('Clientes')->default(false)->live()
                                        ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('pedidos_web', ['ordenes_web','clientes','promociones','despacho'], $get, $set)),
                                    Toggle::make('modulos_activos.promociones')->label('Promociones')->default(false)->live()
                                        ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('pedidos_web', ['ordenes_web','clientes','promociones','despacho'], $get, $set)),
                                    Toggle::make('modulos_activos.despacho')->label('Despachos')->default(false)->live()
                                        ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('pedidos_web', ['ordenes_web','clientes','promociones','despacho'], $get, $set)),
                                ])->columnSpanFull(),
                            ]),

                        // ── COMPRAS ─────────────────────────────────────────────
                        Section::make('Compras')
                            ->icon('heroicon-o-shopping-cart')
                            ->compact()->collapsible()->collapsed()
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

                        // ── CATÁLOGO ────────────────────────────────────────────
                        Section::make('Catálogo de Productos')
                            ->icon('heroicon-o-tag')
                            ->compact()->collapsible()->collapsed()
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

                        // ── REPORTES ────────────────────────────────────────────
                        Section::make('Reportes')
                            ->icon('heroicon-o-document-chart-bar')
                            ->compact()->collapsible()->collapsed()
                            ->schema([
                                Toggle::make('modulos_activos.reportes')
                                    ->label('Activar módulo completo')->onColor('success')->live()->default(true)->columnSpanFull()
                                    ->afterStateUpdated(function (bool $state, Set $set) {
                                        foreach (['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes'] as $s) {
                                            $set("modulos_activos.$s", $state);
                                        }
                                    }),
                                Grid::make(2)->schema([
                                    Toggle::make('modulos_activos.ventas_periodo')->label('Ventas por Período')->default(true)->live()
                                        ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes'], $get, $set)),
                                    Toggle::make('modulos_activos.reporte_ventas')->label('Reporte de Ventas')->default(true)->live()
                                        ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes'], $get, $set)),
                                    Toggle::make('modulos_activos.reporte_ganancias')->label('Reporte de Ganancias')->default(true)->live()
                                        ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes'], $get, $set)),
                                    Toggle::make('modulos_activos.reporte_productos')->label('Productos más vendidos')->default(false)->live()
                                        ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes'], $get, $set)),
                                    Toggle::make('modulos_activos.reporte_compras')->label('Reporte de Compras')->default(true)->live()
                                        ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes'], $get, $set)),
                                    Toggle::make('modulos_activos.reporte_vendedores')->label('Vendedores')->default(true)->live()
                                        ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes'], $get, $set)),
                                    Toggle::make('modulos_activos.reporte_ajustes')->label('Reporte de Ajustes')->default(true)->live()
                                        ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes'], $get, $set)),
                                    Toggle::make('modulos_activos.reporte_clientes')->label('Reporte de Clientes')->default(false)->live()
                                        ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('reportes', ['ventas_periodo','reporte_ventas','reporte_ganancias','reporte_productos','reporte_compras','reporte_vendedores','reporte_ajustes','reporte_clientes'], $get, $set)),
                                ])->columnSpanFull(),
                            ]),

                        // ── GASTOS ──────────────────────────────────────────────
                        Section::make('Gastos')
                            ->icon('heroicon-o-banknotes')
                            ->compact()->collapsible()->collapsed()
                            ->schema([
                                Toggle::make('modulos_activos.gastos')
                                    ->label('Activar Gastos')
                                    ->onColor('success')
                                    ->default(true)
                                    ->columnSpanFull(),
                            ]),

                        // ── RESTAURANTE ─────────────────────────────────────────
                        Section::make('Restaurante')
                            ->icon('heroicon-o-building-storefront')
                            ->compact()->collapsible()->collapsed()
                            ->schema([
                                Toggle::make('modulos_activos.restaurante')
                                    ->label('Activar módulo completo')->onColor('success')->live()->default(false)->columnSpanFull()
                                    ->afterStateUpdated(function (bool $state, Set $set) {
                                        foreach (['mesas','comandas'] as $s) {
                                            $set("modulos_activos.$s", $state);
                                        }
                                    }),
                                Grid::make(2)->schema([
                                    Toggle::make('modulos_activos.mesas')->label('Pisos y Mesas')->default(false)->live()
                                        ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('restaurante', ['mesas','comandas'], $get, $set)),
                                    Toggle::make('modulos_activos.comandas')->label('Comandas')->default(false)->live()
                                        ->afterStateUpdated(fn(Get $get, Set $set) => static::syncPadre('restaurante', ['mesas','comandas'], $get, $set)),
                                ])->columnSpanFull(),
                            ]),

                        // ── CONFIGURACIÓN ───────────────────────────────────────
                        Section::make('Configuración')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->compact()->collapsible()->collapsed()
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

                    ])->columnSpanFull(),
            ]);
    }
}

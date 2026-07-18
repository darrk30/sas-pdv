<?php

namespace App\Filament\Pdv\Pages;

use App\Services\FacturadorService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use UnitEnum;

class MiEmpresaPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-building-office-2';
    protected static ?string                $navigationLabel = 'Mi Empresa';
    protected static ?string                $title           = 'Mi Empresa';
    protected static string|UnitEnum|null   $navigationGroup = 'Configuración';
    protected static ?int                   $navigationSort  = 1;
    protected string                        $view            = 'filament.pdv.pages.mi-empresa';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('cajas.ver') ?? false;
    }

    public function mount(): void
    {
        $empresa     = Filament::getTenant();
        $facturacion = $empresa->facturacion;

        $this->form->fill([
            ...$empresa->only([
                'name', 'ruc', 'email', 'telefono',
                'direccion', 'departamento', 'provincia', 'distrito', 'ubigeo',
                'cod_local', 'country_code',
                'logo', 'icono',
                'carta_activa_cliente',
                'fe_envio_directo_boleta',
                'fe_envio_directo_factura',
                'impresion_comprobante_directo',
                'igv_porcentaje',
            ]),
            'sol_user'       => $facturacion?->sol_user,
            'facturador_url' => $facturacion?->facturador_url,
            'produccion'     => $facturacion?->produccion ?? false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('empresa-tabs')
                    ->tabs([

                        // ── Tab 1: Datos Generales ────────────────────────────
                        Tab::make('Datos Generales')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Section::make()
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Razón Social / Nombre')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        TextInput::make('ruc')
                                            ->label('RUC')
                                            ->required()
                                            ->maxLength(11)
                                            ->minLength(11)
                                            ->numeric()
                                            ->columnSpan(1),

                                        TextInput::make('email')
                                            ->label('Correo electrónico')
                                            ->email()
                                            ->maxLength(255),

                                        TextInput::make('telefono')
                                            ->label('Teléfono')
                                            ->tel()
                                            ->maxLength(20),

                                        TextInput::make('country_code')
                                            ->label('Código de país')
                                            ->default('PE')
                                            ->maxLength(5),
                                    ]),

                                Section::make('Imagen de Marca')
                                    ->icon('heroicon-o-photo')
                                    ->description('El logo aparece en documentos y la barra lateral. El ícono en la pestaña del navegador.')
                                    ->columns(2)
                                    ->schema([
                                        FileUpload::make('logo')
                                            ->label('Logo')
                                            ->helperText('Rectangular. Recomendado: 800×200 px.')
                                            ->image()
                                            ->disk('public')
                                            ->directory('empresas/logos')
                                            ->imageEditor()
                                            ->maxSize(2048)
                                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml']),

                                        FileUpload::make('icono')
                                            ->label('Ícono / Favicon')
                                            ->helperText('Cuadrado. Recomendado: 256×256 px.')
                                            ->image()
                                            ->disk('public')
                                            ->directory('empresas/iconos')
                                            ->imageEditor()
                                            ->maxSize(1024)
                                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/x-icon']),
                                    ]),
                            ]),

                        // ── Tab 2: Ubicación ─────────────────────────────────
                        Tab::make('Ubicación')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Section::make()
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('direccion')
                                            ->label('Dirección')
                                            ->maxLength(255)
                                            ->columnSpanFull(),

                                        TextInput::make('departamento')
                                            ->label('Departamento')
                                            ->maxLength(100),

                                        TextInput::make('provincia')
                                            ->label('Provincia')
                                            ->maxLength(100),

                                        TextInput::make('distrito')
                                            ->label('Distrito')
                                            ->maxLength(100),

                                        TextInput::make('ubigeo')
                                            ->label('Ubigeo (6 dígitos)')
                                            ->maxLength(6),

                                        TextInput::make('cod_local')
                                            ->label('Código de Local SUNAT')
                                            ->default('0000')
                                            ->maxLength(4)
                                            ->helperText('Requerido para facturación electrónica. Normalmente 0000.'),
                                    ]),
                            ]),

                        // ── Tab 3: Catálogo ───────────────────────────────────
                        Tab::make('Catálogo')
                            ->icon('heroicon-o-shopping-bag')
                            ->hidden(function (): bool {
                                $plan = Filament::getTenant()?->suscripcion?->plan;
                                return $plan !== null && ! $plan->tiene_catalogo_web;
                            })
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Select::make('carta_activa_cliente')
                                            ->label('Visibilidad del catálogo para clientes')
                                            ->options([
                                                'activo'   => 'Activo — los clientes pueden ver el catálogo',
                                                'inactivo' => 'Inactivo — el catálogo está oculto al público',
                                            ])
                                            ->native(false)
                                            ->required(),
                                    ]),
                            ]),

                        // ── Tab 4: Facturación Electrónica ────────────────────
                        Tab::make('Facturación Electrónica')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Configuración de Envío')
                                    ->description('Define cuándo y cómo se emiten los comprobantes electrónicos.')
                                    ->columns(2)
                                    ->schema([
                                        Toggle::make('fe_envio_directo_boleta')
                                            ->label('Envío directo de Boletas')
                                            ->helperText('Desactivado: las boletas se acumulan en resumen diario')
                                            ->onColor('success'),

                                        Toggle::make('fe_envio_directo_factura')
                                            ->label('Envío directo de Facturas')
                                            ->helperText('Desactivado: las facturas quedan en estado "Por Enviar"')
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

                                Section::make('Credenciales SOL y Facturador')
                                    ->description('Datos de conexión al servidor de facturación. Las contraseñas en blanco no se modifican.')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('sol_user')
                                            ->label('Usuario SOL')
                                            ->maxLength(20),

                                        TextInput::make('sol_pass')
                                            ->label('Clave SOL')
                                            ->password()
                                            ->revealable()
                                            ->helperText('Dejar en blanco para no cambiar'),

                                        TextInput::make('facturador_url')
                                            ->label('URL del Facturador')
                                            ->url()
                                            ->placeholder('http://facturador.miempresa.com'),

                                        TextInput::make('facturador_api_token')
                                            ->label('Token API del Facturador')
                                            ->password()
                                            ->revealable()
                                            ->helperText('Dejar en blanco para no cambiar'),

                                        FileUpload::make('cert_archivo')
                                            ->label('Certificado digital (.pem)')
                                            ->helperText('Sube el .pem para cargarlo o actualizarlo en el facturador.')
                                            ->disk('local')
                                            ->directory('empresas/certs')
                                            ->visibility('private')
                                            ->acceptedFileTypes(['application/x-pem-file', 'application/octet-stream', 'text/plain'])
                                            ->maxSize(512)
                                            ->columnSpanFull(),

                                        TextInput::make('cert_password')
                                            ->label('Contraseña del certificado')
                                            ->password()
                                            ->revealable()
                                            ->helperText('Solo si tu .pem tiene contraseña. Dejar en blanco para no cambiar.'),

                                        Toggle::make('produccion')
                                            ->label('Entorno de Producción SUNAT')
                                            ->helperText('Desactivado = Beta / homologación')
                                            ->onColor('danger'),
                                    ]),
                            ]),

                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data    = $this->form->getState();
        $empresa = Filament::getTenant();

        $credencialesKeys = ['sol_user', 'sol_pass', 'facturador_url', 'facturador_api_token', 'cert_archivo', 'cert_password', 'produccion'];
        $credenciales     = array_intersect_key($data, array_flip($credencialesKeys));
        $empresaData      = array_diff_key($data, array_flip($credencialesKeys));

        $empresa->update($empresaData);

        // Si se subió un cert nuevo, guardamos su path como cert_path
        $certArchivo = $credenciales['cert_archivo'] ?? null;
        unset($credenciales['cert_archivo']);
        if ($certArchivo) {
            $credenciales['cert_path'] = $certArchivo;
        }

        // Excluir vacíos (contraseñas en blanco = sin cambio)
        $credencialesGuardar = array_filter(
            $credenciales,
            fn($v) => $v !== null && $v !== '' && $v !== [],
        );

        $facturacionExistente = $empresa->facturacion;

        if ($facturacionExistente) {
            if (! empty($credencialesGuardar)) {
                $facturacionExistente->update($credencialesGuardar);
            }
        } elseif (! empty($credencialesGuardar['sol_user']) && ! empty($credencialesGuardar['facturador_url'])) {
            $empresa->facturacion()->create([
                'empresa_id' => $empresa->id,
                ...$credencialesGuardar,
            ]);
        }

        // Sincronizar con el facturador si ya tiene configuración
        $empresa->refresh();
        if ($empresa->facturacion) {
            $resultado = app(FacturadorService::class)->sincronizarEmpresa($empresa);

            if (! $resultado->ok) {
                Notification::make()
                    ->title('Datos guardados')
                    ->body('Los datos se guardaron, pero no se pudo sincronizar con el facturador: ' . $resultado->mensajeError())
                    ->warning()
                    ->send();
                return;
            }
        }

        Notification::make()
            ->title('Empresa actualizada')
            ->body('Los datos se guardaron correctamente.')
            ->success()
            ->send();
    }
}

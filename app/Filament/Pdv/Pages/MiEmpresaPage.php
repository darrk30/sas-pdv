<?php

namespace App\Filament\Pdv\Pages;

use App\Services\FacturadorService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Illuminate\Support\Str;
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
                'bot_contexto',
                'fe_envio_directo_boleta',
                'fe_envio_directo_factura',
                'impresion_comprobante_directo',
                'api_token_impresion',
                'igv_porcentaje',
            ]),
            'sol_user'              => $facturacion?->sol_user,
            'sol_pass'              => $facturacion?->sol_pass,
            'facturador_url'        => $facturacion?->facturador_url,
            'facturador_api_token'  => $facturacion?->facturador_api_token,
            'produccion'            => $facturacion?->produccion ?? false,

            // Datos del Monitor — leídos del .env, nunca de la BD
            '_monitor_link' => $empresa->slug . '.' . env('APP_DOMAIN', ''),
            '_monitor_key'  => config('broadcasting.connections.reverb.key'),
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

                        // ── Tab 3: Configuración ──────────────────────────────
                        Tab::make('Configuración')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([

                                // Sección catálogo — solo si el plan lo incluye
                                Section::make('Catálogo web')
                                    ->icon('heroicon-o-shopping-bag')
                                    ->description('Visibilidad de la tienda en línea para tus clientes.')
                                    ->hidden(function (): bool {
                                        $plan = Filament::getTenant()?->suscripcion?->plan;
                                        return $plan === null || ! $plan->tiene_catalogo_web;
                                    })
                                    ->schema([
                                        Select::make('carta_activa_cliente')
                                            ->label('Estado del catálogo')
                                            ->options([
                                                'activo'   => 'Activo — los clientes pueden ver el catálogo',
                                                'inactivo' => 'Inactivo — el catálogo está oculto al público',
                                            ])
                                            ->native(false),
                                    ]),

                                // Sección impresión directa — solo si el plan la incluye
                                Section::make('Impresión directa')
                                    ->hidden(fn (): bool => ! Filament::getTenant()->tieneImpresionDirecta())
                                    ->icon('heroicon-o-printer')
                                    ->description('Cuando está activa, al registrar una venta se envía automáticamente el comprobante a la impresora de la caja sin abrir el diálogo del navegador.')
                                    ->columns(1)
                                    ->schema([
                                        Toggle::make('impresion_comprobante_directo')
                                            ->label('Impresión directa al emitir comprobante')
                                            ->helperText('Requiere que la caja tenga una impresora asignada y que el Monitor de Impresión esté activo en la PC.')
                                            ->onColor('success'),

                                        TextInput::make('api_token_impresion')
                                            ->label('Token de impresión')
                                            ->helperText('Identifica el canal de esta empresa. No lo compartas. Usa el botón para regenerar.')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->suffixActions([
                                                Action::make('copiar_token')
                                                    ->icon('heroicon-o-clipboard-document')
                                                    ->color('gray')
                                                    ->tooltip('Copiar token')
                                                    ->action(fn () => $this->copiarCampo('api_token_impresion')),
                                                Action::make('generar_token')
                                                    ->label('')
                                                    ->icon('heroicon-o-arrow-path')
                                                    ->color('warning')
                                                    ->tooltip('Generar nuevo token')
                                                    ->requiresConfirmation()
                                                    ->modalHeading('¿Regenerar token de impresión?')
                                                    ->modalDescription('Al regenerar el token, la app Monitor necesitará reconectarse con el nuevo token. ¿Continuar?')
                                                    ->action(fn () => $this->generarTokenImpresion()),
                                            ]),

                                        Section::make('Datos para configurar el Monitor')
                                            ->icon('heroicon-o-computer-desktop')
                                            ->description('Copia estos valores en la app Tukipu Printer al configurarla por primera vez.')
                                            ->compact()
                                            ->columns(2)
                                            ->schema([
                                                TextInput::make('_monitor_link')
                                                    ->label('Servidor (Link)')
                                                    ->helperText('Dominio base del servidor — sin https://')
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->suffixAction(
                                                        Action::make('copiar_link')
                                                            ->icon('heroicon-o-clipboard-document')
                                                            ->color('gray')
                                                            ->tooltip('Copiar')
                                                            ->action(fn () => $this->copiarCampo('_monitor_link'))
                                                    ),

                                                TextInput::make('_monitor_key')
                                                    ->label('Reverb Key')
                                                    ->helperText('Clave pública del WebSocket')
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->suffixAction(
                                                        Action::make('copiar_key')
                                                            ->icon('heroicon-o-clipboard-document')
                                                            ->color('gray')
                                                            ->tooltip('Copiar')
                                                            ->action(fn () => $this->copiarCampo('_monitor_key'))
                                                    ),
                                            ]),
                                    ]),
                            ]),

                        // ── Tab 4: Bot WhatsApp ───────────────────────────────
                        Tab::make('Bot WhatsApp')
                            ->icon('heroicon-o-chat-bubble-left-ellipsis')
                            ->visible(false)
                            ->schema([
                                Section::make('Contexto para el bot')
                                    ->description('Esta información le da contexto al bot de WhatsApp: dirección, horario, formas de pago, datos de Yape/Plin, política de envíos, etc. El bot la puede usar para responder preguntas frecuentes.')
                                    ->schema([
                                        Textarea::make('bot_contexto')
                                            ->label('')
                                            ->placeholder("Ejemplos:\n📍 Estamos en Av. Lima 123, Miraflores\n🕐 Atención: Lunes a Sábado 9am - 8pm\n🛵 Delivery solo Lima Metropolitana\n💳 Yape: 987 654 321 (Juan Pérez)\n💳 Plin: mismo número\n📦 Envíos gratis por compras mayores a S/. 80")
                                            ->rows(10)
                                            ->nullable()
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // ── Tab 5: Facturación Electrónica ────────────────────
                        Tab::make('Facturación Electrónica')
                            ->icon('heroicon-o-document-text')
                            ->visible(fn () => Filament::getTenant()->tieneFacturacionElectronica())
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
                                            ->autocomplete('new-password')
                                            ->helperText('Dejar en blanco para no cambiar'),

                                        TextInput::make('facturador_url')
                                            ->label('URL del Facturador')
                                            ->placeholder('https://facturador.miempresa.com')
                                            ->autocomplete('off'),

                                        TextInput::make('facturador_api_token')
                                            ->label('Token API del Facturador')
                                            ->password()
                                            ->revealable()
                                            ->autocomplete('new-password')
                                            ->helperText('Dejar en blanco para no cambiar'),

                                        Placeholder::make('cert_estado')
                                            ->label('Certificado actual')
                                            ->content(function (): \Illuminate\Support\HtmlString {
                                                $path = Filament::getTenant()->facturacion?->cert_path;
                                                if ($path) {
                                                    $nombre = basename($path);
                                                    return new \Illuminate\Support\HtmlString(
                                                        '<span style="display:inline-flex;align-items:center;gap:.4rem;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;border-radius:.5rem;padding:.3rem .75rem;font-size:.85rem;font-weight:600;">'
                                                        . '<svg xmlns="http://www.w3.org/2000/svg" style="width:1rem;height:1rem;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.955 11.955 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>'
                                                        . htmlspecialchars($nombre)
                                                        . '</span>'
                                                    );
                                                }
                                                return new \Illuminate\Support\HtmlString(
                                                    '<span style="display:inline-flex;align-items:center;gap:.4rem;background:#fef9c3;color:#854d0e;border:1px solid #fde68a;border-radius:.5rem;padding:.3rem .75rem;font-size:.85rem;font-weight:600;">'
                                                    . '<svg xmlns="http://www.w3.org/2000/svg" style="width:1rem;height:1rem;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>'
                                                    . 'Sin certificado cargado'
                                                    . '</span>'
                                                );
                                            })
                                            ->columnSpanFull(),

                                        FileUpload::make('cert_archivo')
                                            ->label('Subir / reemplazar certificado (.pem)')
                                            ->helperText('Deja vacío si no quieres cambiar el certificado actual.')
                                            ->disk('local')
                                            ->directory('empresas/certs')
                                            ->visibility('private')
                                            ->maxSize(512)
                                            ->columnSpanFull(),

                                        TextInput::make('cert_password')
                                            ->label('Contraseña del certificado')
                                            ->password()
                                            ->revealable()
                                            ->autocomplete('new-password')
                                            ->helperText('Solo si tu .pem tiene contraseña. Dejar en blanco para no cambiar.'),

                                        Toggle::make('produccion')
                                            ->label('Entorno de Producción SUNAT')
                                            ->helperText('Desactivado = Beta / homologación')
                                            ->onColor('danger'),
                                    ]),

                                Actions::make([
                                    Action::make('sincronizar_facturador')
                                        ->label('Sincronizar con facturador')
                                        ->icon('heroicon-o-arrow-path')
                                        ->color('info')
                                        ->requiresConfirmation()
                                        ->modalHeading('Sincronizar datos con el facturador')
                                        ->modalDescription('Se enviarán los datos actuales de la empresa al servidor de facturación. ¿Continuar?')
                                        ->action(fn () => $this->sincronizarFacturador()),
                                ]),
                            ]),

                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    // ── Acciones de página ────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [];
    }

    // ── Guardar — solo datos locales, sin sincronizar con el facturador ───────

    public function save(): void
    {
        try {
            $data = $this->form->getState();
        } catch (\Filament\Support\Exceptions\Halt $e) {
            return;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al guardar')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        $empresa = Filament::getTenant();

        $credencialesKeys = [
            'sol_user', 'sol_pass', 'facturador_url', 'facturador_api_token',
            'cert_archivo', 'cert_password', 'produccion', 'cert_estado',
        ];
        $credenciales = array_intersect_key($data, array_flip($credencialesKeys));
        $empresaData  = array_diff_key($data, array_flip($credencialesKeys));

        // api_token_impresion está desactivado en el form (dehydrated:false)
        // → nunca viene en $data, así que no puede sobreescribirse desde el form
        unset($empresaData['api_token_impresion']);

        $empresa->update($empresaData);

        // Guardar credenciales de facturación (contraseñas vacías = sin cambio)
        $certArchivo = $credenciales['cert_archivo'] ?? null;
        unset($credenciales['cert_archivo'], $credenciales['cert_estado']);
        if ($certArchivo) {
            $credenciales['cert_path'] = $certArchivo;
            $this->data['cert_archivo'] = null;
        }

        $credencialesGuardar = array_filter(
            $credenciales,
            fn ($v) => $v !== null && $v !== '' && $v !== [],
        );

        $facturacionExistente = $empresa->facturacion;

        if ($facturacionExistente) {
            if (! empty($credencialesGuardar)) {
                $facturacionExistente->update($credencialesGuardar);
            }
        } elseif (! empty($credencialesGuardar['facturador_url']) || ! empty($credencialesGuardar['facturador_api_token'])) {
            $empresa->facturacion()->create([
                'empresa_id' => $empresa->id,
                ...$credencialesGuardar,
            ]);
        }

        // Refrescar e invalidar cache para que el PDV use los datos nuevos
        $empresa->refresh();
        $empresa->invalidarCacheImpresion();
        $empresa->cachedConfigImpresion(); // pre-calentar

        Notification::make()
            ->title('Empresa actualizada')
            ->body('Los datos se guardaron correctamente.')
            ->success()
            ->send();
    }

    // ── Sincronizar con el facturador (botón exclusivo) ───────────────────────

    public function sincronizarFacturador(): void
    {
        $empresa = Filament::getTenant()->fresh();

        if (! $empresa->facturacion) {
            Notification::make()
                ->title('Sin configuración de facturador')
                ->body('Guarda primero la URL y el token del facturador.')
                ->warning()
                ->send();
            return;
        }

        $resultado = app(FacturadorService::class)->sincronizarEmpresa($empresa);

        if (! $resultado->ok) {
            Notification::make()
                ->title('No se pudo sincronizar')
                ->body($resultado->mensajeError())
                ->danger()
                ->send();
            return;
        }

        Notification::make()
            ->title('Sincronización exitosa')
            ->body('Los datos de la empresa se enviaron correctamente al facturador.')
            ->success()
            ->send();
    }

    // ── Generar nuevo token de impresión ──────────────────────────────────────

    public function copiarCampo(string $campo): void
    {
        $valor = $this->data[$campo] ?? '';
        $this->dispatch('tukipu-copiar', texto: $valor);
        Notification::make()->title('Copiado al portapapeles')->success()->duration(1500)->send();
    }

    public function generarTokenImpresion(): void
    {
        $empresa = Filament::getTenant();

        $nuevoToken = 'tukipu_token_' . Str::uuid();

        $empresa->updateQuietly(['api_token_impresion' => $nuevoToken]);
        $empresa->invalidarCacheImpresion();

        // Actualizar el campo visible en el formulario
        $this->data['api_token_impresion'] = $nuevoToken;

        Notification::make()
            ->title('Token regenerado')
            ->body('El nuevo token de impresión está activo. Reconecta el Monitor con este token.')
            ->success()
            ->send();
    }
}

<?php

namespace App\Filament\Pdv\Pages;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
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
        $empresa = Filament::getTenant();
        $this->form->fill($empresa->only([
            'name', 'email', 'telefono',
            'direccion', 'departamento', 'provincia', 'distrito', 'ubigeo',
            'logo', 'icono',
            'carta_activa_cliente',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Información General')
                    ->icon('heroicon-o-identification')
                    ->description('Datos principales de tu empresa que aparecerán en documentos y el catálogo.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre de la empresa')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('telefono')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(20),
                    ]),

                Section::make('Ubicación')
                    ->icon('heroicon-o-map-pin')
                    ->description('Dirección fiscal o comercial de la empresa.')
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
                            ->label('Ubigeo')
                            ->maxLength(10),
                    ]),

                Section::make('Imagen de Marca')
                    ->icon('heroicon-o-photo')
                    ->description('Sube el logo y el ícono que representarán tu empresa en el sistema y el catálogo.')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('logo')
                            ->label('Logo')
                            ->helperText('Imagen rectangular. Recomendado: 800×200 px. Aparece en la barra lateral.')
                            ->image()
                            ->disk('public')
                            ->directory('empresas/logos')
                            ->imageEditor()
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml']),

                        FileUpload::make('icono')
                            ->label('Ícono / Favicon')
                            ->helperText('Imagen cuadrada. Recomendado: 256×256 px. Aparece en la pestaña del navegador.')
                            ->image()
                            ->disk('public')
                            ->directory('empresas/iconos')
                            ->imageEditor()
                            ->imageCropAspectRatio('1:1')
                            ->maxSize(1024)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/x-icon']),
                    ]),

                Section::make('Catálogo en Línea')
                    ->icon('heroicon-o-shopping-bag')
                    ->description('Controla la visibilidad de tu catálogo público para los clientes.')
                    ->hidden(function (): bool {
                        $plan = Filament::getTenant()?->suscripcion?->plan;
                        return $plan !== null && ! $plan->tiene_catalogo_web;
                    })
                    ->schema([
                        Select::make('carta_activa_cliente')
                            ->label('Estado del catálogo para clientes')
                            ->options([
                                'activo'   => 'Activo — los clientes pueden ver y explorar el catálogo',
                                'inactivo' => 'Inactivo — el catálogo está oculto al público',
                            ])
                            ->native(false)
                            ->required(),
                    ]),

            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data    = $this->form->getState();
        $empresa = Filament::getTenant();
        $empresa->update($data);

        Notification::make()
            ->title('Empresa actualizada')
            ->body('Los datos se guardaron correctamente.')
            ->success()
            ->send();
    }
}

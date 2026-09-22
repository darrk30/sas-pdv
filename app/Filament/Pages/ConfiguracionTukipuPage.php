<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class ConfiguracionTukipuPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-building-office';
    protected static ?string                $navigationLabel = 'Mi Empresa (Tukipu)';
    protected static ?string                $title           = 'Configuración de Tukipu';
    protected static string|UnitEnum|null   $navigationGroup = 'Configuración';
    protected static ?int                   $navigationSort  = 1;
    protected string                        $view            = 'filament.pages.configuracion-tukipu';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) return false;
        // Solo Super Administrador o quien tenga permiso de empresas admin
        return is_null($user->empresa_id) || $user->can('admin.empresas.editar');
    }

    public function mount(): void
    {
        $this->form->fill([
            // Identidad
            'nombre'   => AppSetting::get('nombre',   'Tukipu'),
            'ruc'      => AppSetting::get('ruc'),
            'slogan'   => AppSetting::get('slogan'),
            'logo'     => AppSetting::get('logo')     ? [AppSetting::get('logo')]     : null,
            'favicon'  => AppSetting::get('favicon')  ? [AppSetting::get('favicon')]  : null,

            // Contacto
            'email'     => AppSetting::get('email'),
            'telefono'  => AppSetting::get('telefono'),
            'whatsapp'  => AppSetting::get('whatsapp'),
            'direccion' => AppSetting::get('direccion'),

            // Redes sociales
            'web'       => AppSetting::get('web'),
            'facebook'  => AppSetting::get('facebook'),
            'instagram' => AppSetting::get('instagram'),
            'youtube'   => AppSetting::get('youtube'),
            'linkedin'  => AppSetting::get('linkedin'),
            'tiktok'    => AppSetting::get('tiktok'),
            'twitter'   => AppSetting::get('twitter'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([

                Section::make('Identidad')
                    ->icon('heroicon-o-identification')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre de la empresa')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),

                        TextInput::make('ruc')
                            ->label('RUC')
                            ->maxLength(11)
                            ->numeric(),

                        TextInput::make('slogan')
                            ->label('Slogan')
                            ->maxLength(200),

                        FileUpload::make('logo')
                            ->label('Logo principal')
                            ->image()
                            ->disk('public')
                            ->directory('tukipu')
                            ->imageEditor()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'])
                            ->maxSize(2048)
                            ->helperText('PNG, JPG, SVG o WEBP. Máx. 2 MB.')
                            ->columnSpan(1),

                        FileUpload::make('favicon')
                            ->label('Favicon / Ícono')
                            ->image()
                            ->disk('public')
                            ->directory('tukipu')
                            ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/webp'])
                            ->maxSize(512)
                            ->helperText('PNG o ICO. Máx. 512 KB.')
                            ->columnSpan(1),
                    ]),

                Section::make('Contacto')
                    ->icon('heroicon-o-phone')
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')
                            ->label('Correo de contacto')
                            ->email()
                            ->maxLength(150),

                        TextInput::make('telefono')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(20),

                        TextInput::make('whatsapp')
                            ->label('WhatsApp')
                            ->helperText('Solo el número, ej: 51987654321')
                            ->maxLength(20),

                        Textarea::make('direccion')
                            ->label('Dirección')
                            ->rows(2)
                            ->maxLength(300)
                            ->columnSpanFull(),
                    ]),

                Section::make('Redes Sociales & Web')
                    ->icon('heroicon-o-globe-alt')
                    ->columns(2)
                    ->schema([
                        TextInput::make('web')
                            ->label('Sitio web')
                            ->url()
                            ->prefix('https://')
                            ->maxLength(200),

                        TextInput::make('facebook')
                            ->label('Facebook')
                            ->prefix('facebook.com/')
                            ->maxLength(150),

                        TextInput::make('instagram')
                            ->label('Instagram')
                            ->prefix('@')
                            ->maxLength(100),

                        TextInput::make('tiktok')
                            ->label('TikTok')
                            ->prefix('@')
                            ->maxLength(100),

                        TextInput::make('youtube')
                            ->label('YouTube')
                            ->maxLength(200),

                        TextInput::make('linkedin')
                            ->label('LinkedIn')
                            ->maxLength(200),

                        TextInput::make('twitter')
                            ->label('Twitter / X')
                            ->prefix('@')
                            ->maxLength(100),
                    ]),

            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('guardar')
                ->label('Guardar cambios')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Los FileUpload devuelven array; guardamos el primer elemento (la ruta)
        $logo    = is_array($data['logo'])    ? (reset($data['logo'])    ?: null) : ($data['logo']    ?: null);
        $favicon = is_array($data['favicon']) ? (reset($data['favicon']) ?: null) : ($data['favicon'] ?: null);

        $grupos = [
            'identidad' => [
                'nombre'  => $data['nombre']  ?? null,
                'ruc'     => $data['ruc']     ?? null,
                'slogan'  => $data['slogan']  ?? null,
                'logo'    => $logo,
                'favicon' => $favicon,
            ],
            'contacto' => [
                'email'     => $data['email']     ?? null,
                'telefono'  => $data['telefono']  ?? null,
                'whatsapp'  => $data['whatsapp']  ?? null,
                'direccion' => $data['direccion'] ?? null,
            ],
            'redes' => [
                'web'       => $data['web']       ?? null,
                'facebook'  => $data['facebook']  ?? null,
                'instagram' => $data['instagram'] ?? null,
                'youtube'   => $data['youtube']   ?? null,
                'linkedin'  => $data['linkedin']  ?? null,
                'tiktok'    => $data['tiktok']    ?? null,
                'twitter'   => $data['twitter']   ?? null,
            ],
        ];

        foreach ($grupos as $grupo => $campos) {
            foreach ($campos as $key => $value) {
                AppSetting::set($key, $value, $grupo);
            }
        }

        Notification::make()
            ->title('Configuración guardada')
            ->success()
            ->send();
    }
}

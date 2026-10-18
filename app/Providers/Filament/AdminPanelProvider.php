<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use App\Models\AppSetting;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Storage;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use Spatie\Permission\PermissionRegistrar;

class AdminPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_START,
            fn () => view('components.anuncios-banner'),
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->brandName(fn () => $this->appSetting('nombre', 'Tukipu'))
            ->brandLogo(fn () => $this->appSettingLogo('logo'))
            ->brandLogoHeight('2rem')
            ->favicon(fn () => $this->appSettingLogo('favicon'))
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->maxContentWidth(Width::Full)
            ->navigationGroups([
                NavigationGroup::make('Empresas'),
                NavigationGroup::make('Comercial'),
                NavigationGroup::make('Usuarios'),
                NavigationGroup::make('Sistema')->collapsed(),
                NavigationGroup::make('Configuración')->collapsed(),
            ])
            ->plugins([
                FilamentSpatieLaravelBackupPlugin::make()
                    ->navigationGroup('Sistema')
                    ->navigationSort(10)
                    ->authorize(function (): bool {
                        $user = auth()->user();
                        if (! $user) return false;
                        return \Illuminate\Support\Facades\DB::table('model_has_roles')
                            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                            ->where('model_has_roles.model_id', $user->id)
                            ->where('model_has_roles.model_type', get_class($user))
                            ->whereNull('model_has_roles.empresa_id')
                            ->where('roles.name', 'Super Administrador')
                            ->whereNull('roles.empresa_id')
                            ->exists();
                    }),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    private function appSetting(string $key, mixed $default = null): mixed
    {
        try {
            return AppSetting::get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    private function appSettingLogo(string $key): ?string
    {
        try {
            $path = AppSetting::get($key);
            return $path ? Storage::disk('public')->url($path) : null;
        } catch (\Throwable) {
            return null;
        }
    }

}

<?php

namespace App\Providers\Filament;

use App\Http\Middleware\ValidarEstadoUsuarioEmpresa;
use App\Http\Middleware\VerificarEmpresaActiva;
use App\Models\Empresa;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PdvPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('pdv')
            ->path('pdv')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->brandName(fn() => auth()->user()?->empresas()->first()?->name ?? 'Mi Punto de Venta')
            ->discoverResources(in: app_path('Filament/Pdv/Resources'), for: 'App\Filament\Pdv\Resources')
            ->discoverPages(in: app_path('Filament/Pdv/Pages'), for: 'App\Filament\Pdv\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->login()
            ->profile()
            ->font('Vend Sans', provider: GoogleFontProvider::class)
            ->discoverWidgets(in: app_path('Filament/Pdv/Widgets'), for: 'App\Filament\Pdv\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
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
            ])
            ->assets([
                // Cargamos el script aquí para que esté disponible GLOBALMENTE
                // Js::make('mesas-script', asset('js/mesas.js')),
                // Js::make('orden-mesa-script', asset('js/ordenmesa.js')),
            ])
            ->tenantMiddleware([
                VerificarEmpresaActiva::class,
                ValidarEstadoUsuarioEmpresa::class,
            ], isPersistent: true)
            ->spa()
            ->databaseTransactions()
            ->tenant(Empresa::class, slugAttribute: 'slug')
            ->tenantDomain('{tenant:slug}.' . config('app.domain'));
    }
}

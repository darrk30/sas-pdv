<?php

namespace App\Providers\Filament;

use App\Http\Middleware\ValidarEstadoUsuarioEmpresa;
use App\Http\Middleware\VerificarEmpresaActiva;
use App\Models\Empresa;
use Filament\Facades\Filament;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PdvPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn () => view('filament.pdv.components.barcode-scanner'),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn () => new HtmlString(
                '<style>.fi-no{z-index:10000!important}</style>'
            ),
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('pdv')
            ->path('pdv')
            ->profile(isSimple: false)
            ->colors([
                'primary' => '#46449e',
            ])
            ->brandLogoHeight('3.5rem')
            ->brandName(fn () => Str::limit(Filament::getTenant()?->name ?? 'Tukipu', 22, ''))
            ->brandLogo(function () {
                $logo = Filament::getTenant()?->logo;
                return $logo ? asset('storage/' . $logo) : null;
            })
            ->favicon(function () {
                $icono = Filament::getTenant()?->icono;
                return $icono ? asset('storage/' . $icono) : null;
            })
            ->navigationGroups([
                NavigationGroup::make('Caja'),
                NavigationGroup::make('Productos'),
                NavigationGroup::make('Compras'),
                NavigationGroup::make('Catálogo'),
                NavigationGroup::make('Configuración')->collapsed(),
                NavigationGroup::make('Reportes')->collapsed(),
            ])
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
                Css::make('pdv-punto-de-venta',    asset('css/punto-de-venta.css')),
                Css::make('pdv-ventas-sesion',     asset('css/ventas-sesion.css')),
                Css::make('pdv-cierres-caja',      asset('css/cierres-caja.css')),
                Css::make('pdv-reporte-ventas',    asset('css/reporte-ventas.css')),
                Css::make('pdv-reporte-ganancias', asset('css/reporte-ganancias.css')),
                Css::make('pdv-reporte-productos', asset('css/reporte-productos.css')),
                Css::make('pdv-reporte-compras',   asset('css/reporte-compras.css')),
                Css::make('pdv-kardex',            asset('css/kardex.css')),
                Css::make('pdv-despacho',          asset('css/despacho.css')),
                Css::make('pdv-venta-detalle',     asset('css/venta-detalle-modal.css')),
                Css::make('pdv-cuentas-cobrar',    asset('css/cuentas-por-cobrar.css')),
            ])
            ->tenantMiddleware([
                VerificarEmpresaActiva::class,
                ValidarEstadoUsuarioEmpresa::class,
            ], isPersistent: true)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->spa()
            ->databaseTransactions()
            ->tenant(Empresa::class, slugAttribute: 'slug')
            ->tenantDomain('{tenant:slug}.' . config('app.domain'));
    }
}

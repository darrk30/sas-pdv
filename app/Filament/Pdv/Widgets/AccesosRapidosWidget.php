<?php

namespace App\Filament\Pdv\Widgets;

use App\Filament\Pdv\Pages\DespachoPage;
use App\Filament\Pdv\Pages\PuntoDeVenta;
use App\Filament\Pdv\Resources\Compras\CompraResource;
use App\Filament\Pdv\Resources\Ordenes\OrdenResource;
use App\Filament\Pdv\Resources\Productos\ProductoResource;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class AccesosRapidosWidget extends Widget
{
    protected string $view = 'filament.pdv.widgets.accesos-rapidos';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    public array $accesos = [];

    public function mount(): void
    {
        $empresa = Filament::getTenant();
        $user    = auth()->user();
        $accesos = [];

        if ($empresa->tieneModulo('punto_de_venta') && $user->can('caja.punto_de_venta')) {
            $accesos[] = [
                'label' => 'Punto de Venta',
                'url'   => PuntoDeVenta::getUrl(),
                'icon'  => 'heroicon-o-receipt-percent',
                'color' => 'primary',
            ];
        }

        if ($empresa->tieneModulo('gestion_productos') && $user->can('productos.ver')) {
            $accesos[] = [
                'label' => 'Productos',
                'url'   => ProductoResource::getUrl('index'),
                'icon'  => 'heroicon-o-cube',
                'color' => 'success',
            ];
        }

        if (($empresa->tieneModulo('compras') || $empresa->tieneModulo('gestion_compras')) && $user->can('compras.ver')) {
            $accesos[] = [
                'label' => 'Compras',
                'url'   => CompraResource::getUrl('index'),
                'icon'  => 'heroicon-o-shopping-cart',
                'color' => 'warning',
            ];
        }

        if (($empresa->tieneModulo('ordenes_web') || $empresa->tieneModulo('pedidos_web')) && $user->can('ordenes.ver')) {
            $accesos[] = [
                'label' => 'Órdenes Web',
                'url'   => OrdenResource::getUrl('index'),
                'icon'  => 'heroicon-o-clipboard-document-list',
                'color' => 'info',
            ];
        }

        if ($empresa->tieneModulo('despacho') && $user->can('ordenes.despacho')) {
            $accesos[] = [
                'label' => 'Despachos',
                'url'   => DespachoPage::getUrl(),
                'icon'  => 'heroicon-o-paper-airplane',
                'color' => 'danger',
            ];
        }

        $this->accesos = $accesos;
    }
}

<?php

namespace App\Filament\Pdv\Widgets;

use App\Filament\Pdv\Pages\DespachoPage;
use App\Filament\Pdv\Pages\PuntoDeVenta;
use App\Filament\Pdv\Resources\Compras\CompraResource;
use App\Filament\Pdv\Resources\Ordenes\OrdenResource;
use App\Filament\Pdv\Resources\Productos\ProductoResource;
use Filament\Widgets\Widget;

class AccesosRapidosWidget extends Widget
{
    protected string $view = 'filament.pdv.widgets.accesos-rapidos';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    public array $accesos = [];

    public function mount(): void
    {
        $this->accesos = [
            [
                'label' => 'Punto de Venta',
                'url'   => PuntoDeVenta::getUrl(),
                'icon'  => 'heroicon-o-receipt-percent',
                'color' => 'primary',
            ],
            [
                'label' => 'Productos',
                'url'   => ProductoResource::getUrl('index'),
                'icon'  => 'heroicon-o-cube',
                'color' => 'success',
            ],
            [
                'label' => 'Compras',
                'url'   => CompraResource::getUrl('index'),
                'icon'  => 'heroicon-o-shopping-cart',
                'color' => 'warning',
            ],
            [
                'label' => 'Órdenes Web',
                'url'   => OrdenResource::getUrl('index'),
                'icon'  => 'heroicon-o-clipboard-document-list',
                'color' => 'info',
            ],
            [
                'label' => 'Despachos',
                'url'   => DespachoPage::getUrl(),
                'icon'  => 'heroicon-o-paper-airplane',
                'color' => 'danger',
            ],
        ];
    }
}

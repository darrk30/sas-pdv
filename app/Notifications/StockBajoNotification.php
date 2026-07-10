<?php

namespace App\Notifications;

use App\Models\AjusteDetalle;
use App\Models\Inventario;
use Illuminate\Notifications\Notification;

class StockBajoNotification extends Notification
{
    public function __construct(
        public readonly Inventario $inventario,
        public readonly string $estadoStock, // 'agotado' | 'por_agotarse'
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        $inventario = $this->inventario;
        $producto   = $inventario->producto;

        $nombre = $inventario->variante_id && $inventario->variante
            ? AjusteDetalle::generarNombre($producto, $inventario->variante)
            : $producto->nombre;

        $url = 'http://'
            . $producto->empresa->slug . '.'
            . config('app.domain')
            . '/pdv/gestion-inventario?tableSearch=' . urlencode($producto->nombre);

        $agotado = $this->estadoStock === 'agotado';

        return [
            'title'     => $agotado ? 'Producto agotado' : 'Stock bajo',
            'body'      => $nombre
                . ' — stock actual: '
                . (int) $inventario->stock_real
                . ($agotado ? '' : ' (mínimo: ' . (int) ($inventario->stock_minimo ?? 5) . ')'),
            'icon'      => $agotado
                ? 'heroicon-o-archive-box-x-mark'
                : 'heroicon-o-exclamation-triangle',
            'iconColor' => $agotado ? 'danger' : 'warning',
            'color'     => $agotado ? 'danger' : 'warning',
            'duration'  => 'persistent',
            'format'    => 'filament',
            'actions'   => [
                [
                    'name'                  => 'ver_producto',
                    'label'                 => 'Ver producto',
                    'url'                   => $url,
                    'shouldOpenUrlInNewTab' => false,
                    'shouldMarkAsRead'      => true,
                    'shouldClose'           => false,
                    'color'                 => $agotado ? 'danger' : 'warning',
                    'icon'                  => null,
                    'size'                  => 'sm',
                ],
            ],
        ];
    }
}

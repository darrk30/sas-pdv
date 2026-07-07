<?php

namespace App\Notifications;

use App\Models\Orden;
use Illuminate\Notifications\Notification;

class OrdenNuevaNotification extends Notification
{
    public function __construct(public readonly Orden $orden) {}

    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        $url = 'http://' . $this->orden->empresa->slug . '.' . config('app.domain') . '/pdv/ordenes/' . $this->orden->id . '/edit';

        return [
            'title'     => 'Nueva orden recibida',
            'body'      => $this->orden->codigo
                . ' — ' . $this->orden->cliente_nombre
                . ' — S/ ' . number_format((float) $this->orden->total, 2),
            'icon'      => 'heroicon-o-shopping-bag',
            'iconColor' => 'warning',
            'color'     => 'warning',
            'duration'  => 'persistent',
            'format'    => 'filament',
            'actions'   => [
                [
                    'name'                  => 'ver',
                    'label'                 => 'Ver orden',
                    'url'                   => $url,
                    'shouldOpenUrlInNewTab' => false,
                    'shouldMarkAsRead'      => true,
                    'shouldClose'           => false,
                    'color'                 => 'warning',
                    'icon'                  => null,
                    'size'                  => 'sm',
                ],
            ],
        ];
    }
}

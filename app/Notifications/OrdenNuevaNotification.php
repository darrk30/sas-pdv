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
        $appUrl = config('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'https';
        $domain = env('APP_DOMAIN') ?: parse_url($appUrl, PHP_URL_HOST);
        $url = $scheme . '://' . $this->orden->empresa->slug . '.' . $domain . '/pdv/ordenes/' . $this->orden->id . '/edit';

        return [
            'empresa_id' => $this->orden->empresa_id,
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

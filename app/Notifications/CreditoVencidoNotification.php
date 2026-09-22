<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class CreditoVencidoNotification extends Notification
{
    public function __construct(
        private readonly int    $empresaId,
        private readonly string $titulo,
        private readonly string $cuerpo,
        private readonly string $url,
        private readonly string $actionKey,
        private readonly string $color,   // 'warning' | 'danger'
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'empresa_id' => $this->empresaId,
            'title'      => $this->titulo,
            'body'       => $this->cuerpo,
            'icon'       => $this->color === 'danger'
                ? 'heroicon-o-credit-card'
                : 'heroicon-o-clock',
            'iconColor'  => $this->color,
            'color'      => $this->color,
            'duration'   => 'persistent',
            'format'     => 'filament',
            'actions'    => [
                [
                    'name'                  => $this->actionKey,
                    'label'                 => 'Ver cuentas por cobrar',
                    'url'                   => $this->url,
                    'shouldOpenUrlInNewTab' => false,
                    'shouldMarkAsRead'      => true,
                    'shouldClose'           => true,
                    'color'                 => $this->color,
                    'icon'                  => null,
                    'size'                  => 'sm',
                ],
            ],
        ];
    }
}

<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class GastoFijoVenceNotification extends Notification
{
    public function __construct(
        private readonly int    $empresaId,
        private readonly string $nombre,
        private readonly float  $monto,
        private readonly string $fecha,
        private readonly string $url,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        $montoFmt = 'S/ ' . number_format($this->monto, 2);

        return [
            'empresa_id' => $this->empresaId,
            'title'      => "💸 Gasto fijo vence pronto: {$this->nombre}",
            'body'       => "**{$this->nombre}** — {$montoFmt} vence el **{$this->fecha}**. Recuerda registrar el pago.",
            'icon'       => 'heroicon-o-clipboard-document-list',
            'iconColor'  => 'warning',
            'color'      => 'warning',
            'duration'   => 'persistent',
            'format'     => 'filament',
            'actions'    => [
                [
                    'name'                  => 'ver_gastos_fijos',
                    'label'                 => 'Ver gastos fijos',
                    'url'                   => $this->url,
                    'shouldOpenUrlInNewTab' => false,
                    'shouldMarkAsRead'      => true,
                    'shouldClose'           => true,
                    'color'                 => 'warning',
                    'icon'                  => null,
                    'size'                  => 'sm',
                ],
            ],
        ];
    }
}

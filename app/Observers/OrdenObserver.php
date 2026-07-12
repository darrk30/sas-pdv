<?php

namespace App\Observers;

use App\Models\Orden;
use App\Notifications\OrdenNuevaNotification;
use App\Services\WebPushService;
use Illuminate\Support\Facades\Notification;

class OrdenObserver
{
    public function created(Orden $orden): void
    {
        $orden->loadMissing('empresa');

        $usuarios = $orden->empresa
            ->usuarios()
            ->wherePivot('estado', 'activo')
            ->get();

        if ($usuarios->isEmpty()) {
            return;
        }

        Notification::send($usuarios, new OrdenNuevaNotification($orden));

        // Web Push solo a administradores de esta empresa
        try {
            $body = $orden->codigo
                . ' · ' . ($orden->cliente_nombre ?? 'Cliente')
                . ' · S/ ' . number_format((float) $orden->total, 2);

            $url = 'https://' . $orden->empresa->slug . '.' . config('app.domain')
                . '/pdv/despacho-page';

            app(WebPushService::class)->sendToEmpresaAdmins($orden->empresa_id, 'Nueva orden recibida', $body, $url);
        } catch (\Throwable) {
            // El push nunca debe romper la creación de la orden
        }
    }

    public function updated(Orden $orden): void {}
    public function deleted(Orden $orden): void {}
}

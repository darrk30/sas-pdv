<?php

namespace App\Observers;

use App\Models\Orden;
use App\Notifications\OrdenNuevaNotification;
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
    }

    public function updated(Orden $orden): void {}
    public function deleted(Orden $orden): void {}
}

<?php

namespace App\Observers;

use App\Models\Suscripcion;

class SuscripcionObserver
{
    /**
     * Cuando el admin cambia el plan de una empresa, sincroniza automáticamente
     * los módulos activos de la empresa con los del nuevo plan.
     */
    public function updated(Suscripcion $suscripcion): void
    {
        if (! $suscripcion->wasChanged('plan_id')) {
            return;
        }

        $plan = $suscripcion->plan;

        if (! $plan || empty($plan->modulos_activos)) {
            return;
        }

        $suscripcion->empresa?->update(['modulos_activos' => $plan->modulos_activos]);
    }
}

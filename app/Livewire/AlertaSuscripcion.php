<?php

namespace App\Livewire;

use App\Enums\EstadoGeneral;
use Filament\Facades\Filament;
use Livewire\Component;

class AlertaSuscripcion extends Component
{
    public bool   $proxima      = false;
    public string $diasTexto    = '';
    public string $url          = '';
    public bool   $enPrueba     = false;
    public int    $diasPrueba   = 0;

    public function mount(): void
    {
        $empresa = Filament::getTenant();
        if (! $empresa) {
            return;
        }

        $empresa->loadMissing('suscripcion');
        $suscripcion = $empresa->suscripcion;

        $this->url = route('filament.pdv.pages.mi-suscripcion-page', ['tenant' => $empresa->slug]);

        // Prueba gratuita activa → banner azul siempre visible
        if ($suscripcion
            && $suscripcion->es_prueba_gratuita
            && $suscripcion->estado === EstadoGeneral::Activo
        ) {
            $this->enPrueba   = true;
            $this->diasPrueba = max(0, (int) now()->startOfDay()->diffInDays(
                $suscripcion->fecha_fin->startOfDay(), false
            ));
            return;
        }

        // Suscripción paga próxima a vencer → banner amarillo
        if (! $empresa->suscripcion_proxima_a_vencer) {
            return;
        }

        $this->proxima = true;

        if ($suscripcion?->fecha_fin) {
            $dias            = (int) now()->startOfDay()->diffInDays(
                $suscripcion->fecha_fin->startOfDay(), false
            );
            $this->diasTexto = $dias <= 0 ? 'hoy' : "en {$dias} día" . ($dias === 1 ? '' : 's');
        }
    }

    public function render()
    {
        return view('livewire.alerta-suscripcion');
    }
}

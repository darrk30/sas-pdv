<?php

namespace App\Livewire;

use Filament\Facades\Filament;
use Livewire\Component;

class AlertaSuscripcion extends Component
{
    public bool $proxima    = false;
    public string $diasTexto = '';
    public string $url       = '';

    public function mount(): void
    {
        $empresa = Filament::getTenant();

        if (! $empresa || ! $empresa->suscripcion_proxima_a_vencer) {
            return;
        }

        $this->proxima = true;
        $this->url     = route('filament.pdv.pages.mi-suscripcion-page', ['tenant' => $empresa->slug]);

        $fechaFin = $empresa->suscripcion?->fecha_fin;
        if ($fechaFin) {
            $dias = (int) now()->startOfDay()->diffInDays($fechaFin->startOfDay(), false);
            $this->diasTexto = $dias <= 0 ? 'hoy' : "en {$dias} día" . ($dias === 1 ? '' : 's');
        }
    }

    public function render()
    {
        return view('livewire.alerta-suscripcion');
    }
}

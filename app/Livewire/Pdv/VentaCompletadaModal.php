<?php

namespace App\Livewire\Pdv;

use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Component;

class VentaCompletadaModal extends Component
{
    public bool   $abierto      = false;
    public ?int   $ventaId      = null;
    public string $ventaNumero  = '';
    public float  $ventaTotal   = 0.0;
    /** true = el browser debe auto-imprimir (impresión directa NO ocurrió) */
    public bool   $autoImprimir = false;
    public string $wspTelefono  = '';
    public string $wspShareUrl  = '';

    #[On('abrir-modal-impresion')]
    public function abrir(
        int    $ventaId,
        string $ventaNumero,
        float  $ventaTotal,
        bool   $autoImprimir = true,
        string $shareUrl     = '',
    ): void {
        $this->ventaId      = $ventaId;
        $this->ventaNumero  = $ventaNumero;
        $this->ventaTotal   = $ventaTotal;
        $this->autoImprimir = $autoImprimir;
        $this->wspShareUrl  = $shareUrl;
        $this->wspTelefono  = '';
        $this->abierto      = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->dispatch('modal-impresion-cerrada');
    }

    public function enviarWhatsapp(): void
    {
        $tel = preg_replace('/\D/', '', $this->wspTelefono);

        if (strlen($tel) < 9) {
            Notification::make()->title('Ingresa un número de WhatsApp válido')->warning()->send();
            return;
        }

        if (strlen($tel) === 9) {
            $tel = '51' . $tel;
        }

        $texto = urlencode("Hola! Aquí está tu comprobante ({$this->ventaNumero}): {$this->wspShareUrl}");
        $this->dispatch('pdv-abrir-wsp', url: "https://wa.me/{$tel}?text={$texto}");
    }

    public function render()
    {
        return view('livewire.pdv.venta-completada-modal');
    }
}

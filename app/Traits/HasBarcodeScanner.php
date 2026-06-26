<?php

namespace App\Traits;

use Filament\Notifications\Notification;
use Livewire\Attributes\On;

trait HasBarcodeScanner
{
    #[On('barcode-result')]
    public function handleBarcodeResult(string $path, string $code): void
    {
        data_set($this->data, $path, $code);
    }

    #[On('camera-not-available')]
    public function handleCameraNotAvailable(): void
    {
        Notification::make()
            ->title('Cámara no disponible')
            ->body('Activa los permisos de cámara en el navegador o usa un escáner USB conectado al equipo.')
            ->warning()
            ->send();
    }
}

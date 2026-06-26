<?php

namespace App\Traits;

use Livewire\Attributes\On;

trait HasBarcodeScanner
{
    #[On('barcode-result')]
    public function handleBarcodeResult(string $path, string $code): void
    {
        // $path llega sin el prefijo 'data.' (ya se quita en el JS)
        data_set($this->data, $path, $code);
    }
}

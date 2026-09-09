<?php

namespace App\Livewire\Pdv;

use Livewire\Attributes\On;
use Livewire\Component;

class ComandaModal extends Component
{
    public bool   $abierto  = false;
    public int    $ordenId  = 0;
    public string $mesa     = '';
    public string $cajero   = '';
    public bool   $parcial  = false;

    /** [ ['nombre'=>'Cocina','nuevos'=>[...],'cancelados'=>[...]], ... ] */
    public array $areas = [];

    #[On('imprimir-comanda-browser')]
    public function abrir(
        int    $ordenId,
        string $areasJson = '[]',
        string $mesa      = '',
        string $cajero    = '',
        bool   $parcial   = false,
    ): void {
        $areas = json_decode($areasJson, true) ?? [];

        if (empty($areas)) {
            return;
        }

        $this->ordenId = $ordenId;
        $this->areas   = $areas;
        $this->mesa    = $mesa;
        $this->cajero  = $cajero;
        $this->parcial = $parcial;
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->dispatch('comanda-modal-cerrada');
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.pdv.comanda-modal');
    }
}

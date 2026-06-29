<?php

namespace App\Livewire\Tienda\Partials;

use App\Models\Marca;
use Livewire\Attributes\Url;
use Livewire\Component;

class Marcas extends Component
{
    public int $empresaId = 0;

    #[Url(as: 'marca')]
    public int $marcaId = 0;

    public function mount(): void
    {
        $this->empresaId = app('tienda.empresa')->id;
    }

    public function seleccionar(int $id): void
    {
        $nuevo = $this->marcaId === $id ? 0 : $id;

        $this->redirect($nuevo ? '/?marca=' . $nuevo : '/', navigate: true);
    }

    public function render()
    {
        $marcas = Marca::where('empresa_id', $this->empresaId)
            ->where('estado', true)
            ->orderBy('nombre')
            ->get();

        return view('livewire.tienda.partials.marcas', compact('marcas'));
    }
}

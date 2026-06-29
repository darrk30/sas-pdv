<?php

namespace App\Livewire\Tienda\Partials;

use App\Models\Categoria;
use Livewire\Attributes\Url;
use Livewire\Component;

class Categorias extends Component
{
    public int $empresaId = 0;

    #[Url(as: 'cat')]
    public int $categoriaId = 0;

    public function mount(): void
    {
        $this->empresaId = app('tienda.empresa')->id;
    }

    public function seleccionar(int $id): void
    {
        $nuevo = $this->categoriaId === $id ? 0 : $id;

        $this->redirect($nuevo ? '/?cat=' . $nuevo : '/', navigate: true);
    }

    public function render()
    {
        $categorias = Categoria::where('empresa_id', $this->empresaId)
            ->where('estado', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->withCount(['productos' => fn($q) => $q->where('estado', 'activo')])
            ->get();

        return view('livewire.tienda.partials.categorias', compact('categorias'));
    }
}

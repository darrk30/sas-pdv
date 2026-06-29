<?php

namespace App\Livewire\Tienda;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::tienda')]
#[Title('Catálogo')]
class Catalogo extends Component
{
    use WithPagination;

    public int $empresaId = 0;

    #[Url(as: 'q', history: true)]
    public string $buscar = '';

    #[Url(as: 'marca', history: true)]
    public int $marcaId = 0;

    #[Url(as: 'cat', history: true)]
    public int $categoriaId = 0;

    public function mount(): void
    {
        $this->empresaId = app('tienda.empresa')->id;
    }

    public function updatingBuscar(): void    { $this->resetPage(); }
    public function updatingMarcaId(): void   { $this->resetPage(); }
    public function updatingCategoriaId(): void { $this->resetPage(); }

    public function limpiarMarca(): void
    {
        $this->marcaId = 0;
        $this->resetPage();
    }

    public function limpiarCategoria(): void
    {
        $this->categoriaId = 0;
        $this->resetPage();
    }

    public function render()
    {
        $productos = Producto::where('empresa_id', $this->empresaId)
            ->where('estado', 'activo')
            ->when($this->buscar,      fn($q) => $q->where('nombre', 'like', '%' . $this->buscar . '%'))
            ->when($this->marcaId,     fn($q) => $q->where('marca_id', $this->marcaId))
            ->when($this->categoriaId, fn($q) => $q->where('categoria_id', $this->categoriaId))
            ->with([
                'categoria',
                'atributos.atributo',
                'atributos.valores',
                'galeriaProductos',
                'variantes' => fn($q) => $q->where('estado', 'activo')
                    ->whereNotNull('imagen')
                    ->with('valores'),
            ])
            ->paginate(24);

        $marcaActiva     = $this->marcaId     ? Marca::find($this->marcaId)?->nombre         : null;
        $categoriaActiva = $this->categoriaId ? Categoria::find($this->categoriaId)?->nombre : null;

        $tieneCategorias = Categoria::where('empresa_id', $this->empresaId)
            ->where('estado', true)
            ->exists();

        return view('livewire.tienda.catalogo', compact(
            'productos', 'marcaActiva', 'categoriaActiva', 'tieneCategorias'
        ));
    }
}

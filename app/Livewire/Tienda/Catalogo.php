<?php

namespace App\Livewire\Tienda;

use App\Enums\EstadoPromocion;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Promocion;
use Carbon\Carbon;
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

    public function recibirBusqueda(string $q): void
    {
        $this->buscar = trim($q);
        $this->resetPage();
    }
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
            ->where('visible_en_carta', true)
            ->when($this->buscar, fn($q) => $q->where(fn($q) =>
                $q->where('nombre', 'like', '%' . $this->buscar . '%')
                  ->orWhere('codigo_interno', 'like', '%' . $this->buscar . '%')
            ))
            ->when($this->marcaId,     fn($q) => $q->where('marca_id', $this->marcaId))
            ->when($this->categoriaId, fn($q) => $q->where('categoria_id', $this->categoriaId))
            ->with([
                'categoria',
                'atributos.atributo',
                'atributos.valores',
                'galeriaProductos',
                'inventario',
                'variantes' => fn($q) => $q->where('estado', 'activo')->with(['valores', 'inventario']),
            ])
            ->orderByRaw('
                CASE
                    WHEN productos.control_de_stock = 0 THEN 0
                    WHEN productos.venta_sin_stock  = 1 THEN 0
                    WHEN EXISTS (
                        SELECT 1 FROM variantes v
                        WHERE v.producto_id = productos.id AND v.estado = ?
                    ) THEN
                        -- Producto con variantes: solo sumar stock de variantes activas
                        CASE WHEN (
                            SELECT COALESCE(SUM(i.stock_reserva), 0)
                            FROM inventarios i
                            INNER JOIN variantes v ON i.variante_id = v.id
                            WHERE v.producto_id = productos.id
                              AND v.estado      = ?
                              AND i.empresa_id  = productos.empresa_id
                        ) > 0 THEN 0 ELSE 1 END
                    ELSE
                        -- Producto simple: inventario base (sin variante)
                        CASE WHEN (
                            SELECT COALESCE(SUM(i.stock_reserva), 0)
                            FROM inventarios i
                            WHERE i.producto_id = productos.id
                              AND i.variante_id IS NULL
                              AND i.empresa_id  = productos.empresa_id
                        ) > 0 THEN 0 ELSE 1 END
                END ASC
            ', ['activo', 'activo'])
            ->latest()
            ->paginate(25);

        $marcaActiva     = $this->marcaId     ? Marca::find($this->marcaId)?->nombre         : null;
        $categoriaActiva = $this->categoriaId ? Categoria::find($this->categoriaId)?->nombre : null;

        $tieneCategorias = Categoria::where('empresa_id', $this->empresaId)
            ->where('estado', true)
            ->exists();

        // Promociones vigentes hoy (solo sin filtros activos para no confundir)
        $hoy = Carbon::today();
        $diaSemana = (string) Carbon::now()->dayOfWeekIso;

        $promociones = (!$this->buscar && !$this->marcaId && !$this->categoriaId && $this->getPage() <= 1)
            ? Promocion::where('empresa_id', $this->empresaId)
                ->where('estado', EstadoPromocion::Activo)
                ->where(fn($q) => $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $hoy))
                ->where(fn($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $hoy))
                ->where(fn($q) => $q->whereNull('limite_usos')->orWhereColumn('usos_actuales', '<', 'limite_usos'))
                ->with(['detalles.producto.inventario', 'detalles.variante.producto.inventario', 'detalles.variante.inventario'])
                ->get()
                ->filter(fn($p) => empty($p->dias_semana) ||
                    in_array($diaSemana, array_map('strval', $p->dias_semana)))
                ->values()
            : collect();

        return view('livewire.tienda.catalogo', compact(
            'productos', 'promociones', 'marcaActiva', 'categoriaActiva', 'tieneCategorias'
        ));
    }
}

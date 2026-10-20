<?php

namespace App\Livewire\Tienda;

use App\Enums\EstadoPromocion;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Promocion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    public function updatingPage(): void        { $this->dispatch('scroll-top'); }

    public function updatingBuscar(): void    { $this->resetPage(); }

    private function fullTextQuery(): string
    {
        return collect(preg_split('/\s+/', trim($this->buscar)))
            ->filter(fn($w) => mb_strlen($w) >= 2)
            ->map(fn($w) => mb_strlen($w) >= 4 ? $w . '*' : $w)
            ->implode(' ');
    }

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
            ->when($this->buscar, function ($q) {
                $like = '%' . $this->buscar . '%';
                $ft   = $this->fullTextQuery();
                $q->where(fn($q) =>
                    $q->whereRaw('MATCH(productos.nombre, productos.descripcion) AGAINST(? IN BOOLEAN MODE)', [$ft])
                      ->orWhere('productos.nombre', 'like', $like)
                      ->orWhere('productos.codigo_interno', 'like', $like)
                      ->orWhereHas('categoria', fn($cq) => $cq->where('nombre', 'like', $like))
                      ->orWhereHas('marca',     fn($mq) => $mq->where('nombre', 'like', $like))
                );
            })
            ->when($this->marcaId,     fn($q) => $q->where('marca_id', $this->marcaId))
            ->when($this->categoriaId, fn($q) => $q->where('categoria_id', $this->categoriaId))
            ->where(fn($q) => $q
                ->whereNull('categoria_id')
                ->orWhereHas('categoria', fn($cq) => $cq->where('estado', true)->where('activo_en_catalogo', true))
            )
            ->with([
                'categoria',
                'marca',
                'atributos.atributo',
                'atributos.valores',
                'galeriaProductos',
                'inventario',
                'variantes' => fn($q) => $q->where('estado', 'activo')->with(['valores', 'inventario']),
            ])
            ->select('productos.*')
            ->leftJoinSub(
                DB::table('inventarios as i')
                    ->leftJoin('variantes as v', fn ($j) =>
                        $j->on('v.id', '=', 'i.variante_id')->where('v.estado', 'activo')
                    )
                    ->leftJoin('productos as p', 'p.id', '=', DB::raw('COALESCE(v.producto_id, i.producto_id)'))
                    ->where('i.empresa_id', $this->empresaId)
                    ->selectRaw('
                        COALESCE(v.producto_id, i.producto_id) AS p_id,
                        CASE
                            WHEN MAX(CASE WHEN p.comparte_stock = 1 THEN 1 ELSE 0 END) = 1
                            THEN SUM(CASE WHEN i.variante_id IS NULL THEN i.stock_reserva ELSE 0 END)
                            WHEN MAX(CASE WHEN v.id IS NOT NULL THEN 1 ELSE 0 END) = 1
                            THEN SUM(CASE WHEN v.id IS NOT NULL THEN i.stock_reserva ELSE 0 END)
                            ELSE SUM(CASE WHEN i.variante_id IS NULL THEN i.stock_reserva ELSE 0 END)
                        END AS stk
                    ')
                    ->groupByRaw('COALESCE(v.producto_id, i.producto_id)'),
                '_stk',
                '_stk.p_id',
                '=',
                'productos.id'
            )
            ->orderByRaw('
                CASE
                    WHEN productos.control_de_stock = 0 THEN 0
                    WHEN productos.venta_sin_stock  = 1 THEN 0
                    WHEN COALESCE(_stk.stk, 0) > 0    THEN 0
                    ELSE 1
                END ASC
            ')
            ->orderBy('productos.orden')
            ->orderBy('productos.nombre')
            ->paginate(24);

        $marcaActiva = $this->marcaId
            ? Cache::remember("tienda_marca_{$this->marcaId}", 600, fn () => Marca::find($this->marcaId)?->nombre)
            : null;

        $categoriaActiva = $this->categoriaId
            ? Cache::remember("tienda_cat_{$this->categoriaId}", 600, fn () => Categoria::find($this->categoriaId)?->nombre)
            : null;

        $tieneCategorias = Cache::remember("tienda_tiene_cats_{$this->empresaId}", 300, fn () =>
            Categoria::where('empresa_id', $this->empresaId)->where('estado', true)->where('activo_en_catalogo', true)->exists()
        );

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

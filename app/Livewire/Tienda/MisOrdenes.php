<?php

namespace App\Livewire\Tienda;

use App\Models\Orden;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::tienda')]
#[Title('Mis órdenes')]
class MisOrdenes extends Component
{
    use WithPagination;

    public int $empresaId = 0;

    public ?Orden $ordenDetalle = null;

    public function mount(): void
    {
        if (! Auth::guard('cliente')->check()) {
            $this->redirect(route('tienda.login'), navigate: true);
            return;
        }

        $this->empresaId = app('tienda.empresa')->id;
    }

    public function abrirDetalle(int $id): void
    {
        $clienteId = Auth::guard('cliente')->id();

        $this->ordenDetalle = Orden::where('empresa_id', $this->empresaId)
            ->where('cliente_id', $clienteId)
            ->where('id', $id)
            ->with(['detalles', 'metodoEnvio', 'metodoPago'])
            ->first();
    }

    public function cerrarDetalle(): void
    {
        $this->ordenDetalle = null;
    }

    public function render()
    {
        $clienteId = Auth::guard('cliente')->id();

        $ordenes = Orden::where('empresa_id', $this->empresaId)
            ->where('cliente_id', $clienteId)
            ->with(['metodoEnvio', 'metodoPago'])
            ->withCount('detalles')
            ->orderByDesc('fecha_orden')
            ->paginate(10);

        return view('livewire.tienda.mis-ordenes', compact('ordenes'));
    }
}

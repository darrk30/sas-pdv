<?php

namespace App\Livewire\Tienda\Partials;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class Navbar extends Component
{
    public string $empresaNombre = '';
    public string $empresaLogo   = '';
    public int    $carritoCount  = 0;

    public function mount(): void
    {
        $empresa             = app('tienda.empresa');
        $this->empresaNombre = $empresa->nombre ?? 'Tienda';
        $this->empresaLogo   = $empresa->logo
            ? Storage::url($empresa->logo)
            : '';

        $this->carritoCount = count(session("carrito.{$empresa->id}", []));
    }

    public function buscar(string $termino): void
    {
        $q = trim($termino);
        $this->redirect($q ? '/?q=' . urlencode($q) : '/', navigate: true);
    }

    public function logout(): void
    {
        Auth::guard('cliente')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/', navigate: true);
    }

    public function render()
    {
        return view('livewire.tienda.partials.navbar', [
            'cliente' => Auth::guard('cliente')->user(),
        ]);
    }
}

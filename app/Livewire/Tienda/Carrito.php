<?php

namespace App\Livewire\Tienda;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::tienda')]
#[Title('Mi carrito')]
class Carrito extends Component
{
    public function render()
    {
        $cliente = Auth::guard('cliente')->user();

        return view('livewire.tienda.carrito', compact('cliente'));
    }
}

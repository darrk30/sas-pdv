<?php

namespace App\Livewire\Tienda\Auth;

use App\Models\Cliente;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::tienda-auth')]
#[Title('Crear cuenta')]
class Registro extends Component
{
    public int $empresaId = 0;

    #[Validate('required|string|max:255')]
    public string $nombre = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('required|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->empresaId = app('tienda.empresa')->id;
    }

    public function registrar(): void
    {
        $this->validate();

        $existe = Cliente::where('empresa_id', $this->empresaId)
            ->where('email', $this->email)
            ->exists();

        if ($existe) {
            $this->addError('email', 'Este correo ya está registrado.');
            return;
        }

        $cliente = Cliente::create([
            'empresa_id' => $this->empresaId,
            'nombre'     => $this->nombre,
            'email'      => $this->email,
            'password'   => $this->password,
        ]);

        Auth::guard('cliente')->login($cliente);

        $this->redirect(route('tienda.catalogo'), navigate: true);
    }

    public function render()
    {
        return view('livewire.tienda.auth.registro');
    }
}

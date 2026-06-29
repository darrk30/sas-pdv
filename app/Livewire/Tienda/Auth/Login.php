<?php

namespace App\Livewire\Tienda\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::tienda-auth')]
#[Title('Iniciar sesión')]
class Login extends Component
{
    public int $empresaId = 0;

    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required')]
    public string $password = '';

    public bool $recordar = false;

    public function mount(): void
    {
        $this->empresaId = app('tienda.empresa')->id;
    }

    public function login(): void
    {
        $this->validate();

        $ok = Auth::guard('cliente')->attempt([
            'email'      => $this->email,
            'password'   => $this->password,
            'empresa_id' => $this->empresaId,
        ], $this->recordar);

        if ($ok) {
            session()->regenerate();
            $this->redirect(route('tienda.catalogo'), navigate: true);
            return;
        }

        $this->addError('email', 'Correo o contraseña incorrectos.');
        $this->reset('password');
    }

    public function render()
    {
        return view('livewire.tienda.auth.login');
    }
}

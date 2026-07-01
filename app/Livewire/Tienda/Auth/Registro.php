<?php

namespace App\Livewire\Tienda\Auth;

use App\Models\Cliente;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::tienda-auth')]
#[Title('Crear cuenta')]
class Registro extends Component
{
    public int $empresaId = 0;

    public string $nombre    = '';
    public string $apellidos = '';
    public string $dni       = '';
    public string $email     = '';
    public string $password  = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->empresaId = app('tienda.empresa')->id;
    }

    protected function rules(): array
    {
        return [
            'nombre'    => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'dni'       => 'required|digits:8',
            'email'     => 'required|email|max:255',
            'password'  => ['required', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ];
    }

    protected function messages(): array
    {
        return [
            'nombre.required'    => 'El nombre es obligatorio.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'dni.required'       => 'El DNI es obligatorio.',
            'dni.digits'         => 'El DNI debe tener exactamente 8 dígitos.',
            'email.required'     => 'El correo electrónico es obligatorio.',
            'email.email'        => 'Ingresa un correo válido.',
            'password.required'  => 'La contraseña es obligatoria.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }

    public function registrar(): void
    {
        $this->validate();

        // Verificar si el email ya lo usa otro cliente registrado
        if (Cliente::where('empresa_id', $this->empresaId)
            ->where('email', $this->email)
            ->whereNotNull('password')
            ->exists()
        ) {
            $this->addError('email', 'Este correo ya está registrado.');
            return;
        }

        // Si el DNI existe (cliente guest creado al hacer una orden), completar su registro
        $cliente = Cliente::where('empresa_id', $this->empresaId)
            ->where('numero_documento', $this->dni)
            ->first();

        if ($cliente) {
            // Ya tiene cuenta activa
            if ($cliente->email && $cliente->password) {
                $this->addError('dni', 'Este DNI ya tiene una cuenta registrada.');
                return;
            }

            $cliente->update([
                'nombre'    => $this->nombre,
                'apellidos' => $this->apellidos,
                'email'     => $this->email,
                'correo'    => $this->email,
                'password'  => $this->password,
            ]);
        } else {
            $cliente = Cliente::create([
                'empresa_id'       => $this->empresaId,
                'nombre'           => $this->nombre,
                'apellidos'        => $this->apellidos,
                'tipo_documento'   => 'dni',
                'numero_documento' => $this->dni,
                'email'            => $this->email,
                'correo'           => $this->email,
                'password'         => $this->password,
            ]);
        }

        Auth::guard('cliente')->login($cliente);

        $this->redirect(route('tienda.catalogo'), navigate: true);
    }

    public function render()
    {
        return view('livewire.tienda.auth.registro');
    }
}

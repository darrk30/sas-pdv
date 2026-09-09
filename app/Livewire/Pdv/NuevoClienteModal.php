<?php

namespace App\Livewire\Pdv;

use App\Models\Cliente;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class NuevoClienteModal extends Component
{
    public bool   $abierto      = false;
    public string $ncNombre     = '';
    public string $ncApellidos  = '';
    public string $ncTipoDoc    = 'dni';
    public string $ncNumeroDoc  = '';
    public string $ncTelefono   = '';
    public string $ncDireccion  = '';
    public string $ncDepartamento = '';
    public string $ncProvincia  = '';
    public string $ncDistrito   = '';

    #[On('abrir-modal-nuevo-cliente')]
    public function abrir(): void
    {
        $this->reset([
            'ncNombre', 'ncApellidos', 'ncNumeroDoc',
            'ncTelefono', 'ncDireccion', 'ncDepartamento',
            'ncProvincia', 'ncDistrito',
        ]);
        $this->ncTipoDoc = 'dni';
        $this->resetValidation();
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
    }

    public function updatedNcTipoDoc(): void
    {
        $this->ncNumeroDoc = '';
        $this->resetValidation('ncNumeroDoc');
    }

    public function crear(): void
    {
        $longitud  = $this->ncTipoDoc === 'ruc' ? 11 : 8;
        $tipoLabel = strtoupper($this->ncTipoDoc);

        $this->validate([
            'ncNombre'    => 'required|string|max:255',
            'ncNumeroDoc' => [
                'required',
                "digits:{$longitud}",
                Rule::unique('clientes', 'numero_documento')
                    ->where('empresa_id', Filament::getTenant()->id),
            ],
        ], [
            'ncNombre.required'    => 'El nombre es requerido.',
            'ncNumeroDoc.required' => 'El número de documento es requerido.',
            'ncNumeroDoc.digits'   => "El {$tipoLabel} debe tener {$longitud} dígitos.",
            'ncNumeroDoc.unique'   => 'Este número de documento ya está registrado.',
        ]);

        $cliente = Cliente::create([
            'empresa_id'       => Filament::getTenant()->id,
            'user_id'          => auth()->id(),
            'nombre'           => $this->ncNombre,
            'apellidos'        => $this->ncApellidos ?: null,
            'tipo_documento'   => $this->ncTipoDoc,
            'numero_documento' => $this->ncNumeroDoc,
            'telefono'         => $this->ncTelefono ?: null,
            'direccion'        => $this->ncDireccion ?: null,
            'departamento'     => $this->ncDepartamento ?: null,
            'provincia'        => $this->ncProvincia ?: null,
            'distrito'         => $this->ncDistrito ?: null,
        ]);

        $this->cerrar();
        $this->dispatch('cliente-creado', id: $cliente->id);
        Notification::make()->title('Cliente creado y seleccionado')->success()->send();
    }

    public function render()
    {
        return view('livewire.pdv.nuevo-cliente-modal');
    }
}

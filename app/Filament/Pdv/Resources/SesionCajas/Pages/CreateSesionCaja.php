<?php

namespace App\Filament\Pdv\Resources\SesionCajas\Pages;

use App\Enums\EstadoMovimiento;
use App\Enums\EstadoSesion;
use App\Enums\TipoMovimiento;
use App\Filament\Pdv\Resources\SesionCajas\SesionCajaResource;
use App\Filament\Pdv\Resources\SesionCajas\Schemas\SesionCajaForm;
use App\Models\MetodoPago;
use App\Models\SesionCaja;
use App\Models\Transaccion;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class CreateSesionCaja extends CreateRecord
{
    protected static string $resource = SesionCajaResource::class;

    public function mount(): void
    {
        // Si ya hay una sesión abierta para alguna caja del usuario → ir a cerrarla
        $sesionAbierta = SesionCaja::where('empresa_id', Filament::getTenant()->id)
            ->where('user_id', auth()->id())
            ->where('estado', EstadoSesion::Abierta->value)
            ->first();

        if ($sesionAbierta) {
            Notification::make()
                ->info()
                ->title('Ya tienes una sesión abierta')
                ->body('Serás redirigido para cerrarla.')
                ->send();

            $this->redirect(static::getResource()::getUrl('edit', ['record' => $sesionAbierta->getKey()]));
            return;
        }

        parent::mount();
    }

    public function form(Schema $schema): Schema
    {
        return SesionCajaForm::configureApertura($schema);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Validar que la caja no tenga ya una sesión abierta (otro usuario)
        $existe = SesionCaja::where('caja_id', $data['caja_id'])
            ->where('estado', EstadoSesion::Abierta->value)
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'data.caja_id' => 'Esta caja ya tiene una sesión abierta.',
            ]);
        }

        $data['estado'] = EstadoSesion::Abierta->value;

        return $data;
    }

    protected function afterCreate(): void
    {
        $sesion = $this->record;

        if ((float) $sesion->monto_apertura <= 0) return;

        // Buscar el método de pago efectivo de la empresa (por nombre, insensible a mayúsculas)
        $efectivo = MetodoPago::where('empresa_id', $sesion->empresa_id)
            ->whereRaw('LOWER(nombre) LIKE ?', ['%efectivo%'])
            ->first();

        Transaccion::create([
            'empresa_id'           => $sesion->empresa_id,
            'sesion_caja_id'       => $sesion->id,
            'transaccionable_type' => SesionCaja::class,
            'transaccionable_id'   => $sesion->id,
            'tipo'                 => TipoMovimiento::Ingreso,
            'concepto'             => 'Fondo de apertura',
            'monto'                => $sesion->monto_apertura,
            'metodo_pago_id'       => $efectivo?->id,
            'estado'               => EstadoMovimiento::Aprobado,
            'fecha'                => $sesion->fecha_apertura,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

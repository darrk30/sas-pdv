<?php

namespace App\Filament\Pdv\Resources\SesionCajas\Pages;

use App\Enums\EstadoSesion;
use App\Filament\Pdv\Resources\SesionCajas\SesionCajaResource;
use App\Filament\Pdv\Resources\SesionCajas\Schemas\SesionCajaForm;
use App\Models\SesionCaja;
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

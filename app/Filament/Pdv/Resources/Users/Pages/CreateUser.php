<?php

namespace App\Filament\Pdv\Resources\Users\Pages;

use App\Enums\EstadoGeneral;
use App\Filament\Pdv\Resources\Users\UserResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public function mount(): void
    {
        $empresa = Filament::getTenant();
        $plan    = $empresa?->suscripcion?->plan;

        if ($plan && $empresa->usuarios()->count() >= $plan->maximo_usuarios) {
            Notification::make()
                ->warning()
                ->title('Límite de usuarios alcanzado')
                ->body("Tu plan permite hasta {$plan->maximo_usuarios} usuario(s). Contacta al administrador para ampliar tu plan.")
                ->persistent()
                ->send();

            $this->redirect(UserResource::getUrl('index'));
            return;
        }

        parent::mount();
    }

    protected function beforeCreate(): void
    {
        $empresa = Filament::getTenant();
        $plan    = $empresa?->suscripcion?->plan;

        if (! $plan) return;

        if ($empresa->usuarios()->count() >= $plan->maximo_usuarios) {
            Notification::make()
                ->warning()
                ->title('Límite de usuarios alcanzado')
                ->body("Tu plan permite hasta {$plan->maximo_usuarios} usuario(s). Contacta al administrador para ampliar tu plan.")
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    protected function afterCreate(): void
    {
        $state     = $this->form->getRawState();
        $empresaId = filament()->getTenant()?->id;

        if ($empresaId && isset($state['pivot_estado'])) {
            $this->record->empresas()->syncWithoutDetaching([
                $empresaId => ['estado' => $state['pivot_estado']],
            ]);
        }
    }
}

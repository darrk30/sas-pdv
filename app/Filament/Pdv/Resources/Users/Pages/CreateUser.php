<?php

namespace App\Filament\Pdv\Resources\Users\Pages;

use App\Enums\EstadoGeneral;
use App\Filament\Pdv\Resources\Users\UserResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $state = $this->form->getRawState();
        $empresaId = filament()->getTenant()?->id;
        if ($empresaId && isset($state['pivot_estado'])) {
            $this->record->empresas()->syncWithoutDetaching([
                $empresaId => ['estado' => $state['pivot_estado']]
            ]);
        }
    }
}

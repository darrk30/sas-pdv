<?php

namespace App\Filament\Pdv\Resources\Users\Pages;

use App\Enums\EstadoGeneral;
use App\Filament\Pdv\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $empresaId = filament()->getTenant()?->id;
        if ($empresaId) {
            $empresa = $this->record->empresas()->where('empresas.id', $empresaId)->first();
            if ($empresa && $empresa->pivot) {
                $data['pivot_estado'] = $empresa->pivot->estado;
            }
        }
        return $data;
    }

    protected function afterSave(): void
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

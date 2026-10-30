<?php

namespace App\Filament\Pdv\Resources\TareasProgramadasPdv\Pages;

use App\Filament\Pdv\Resources\TareasProgramadasPdv\TareaProgramadaPdvResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTareaProgramadaPdv extends EditRecord
{
    protected static string $resource = TareaProgramadaPdvResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['dias_semana'])) {
            $data['dias_semana'] = null;
        }

        return $data;
    }
}

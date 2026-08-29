<?php

namespace App\Filament\Resources\TareasProgramadas\Pages;

use App\Filament\Resources\TareasProgramadas\TareaProgramadaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTareaProgramada extends EditRecord
{
    protected static string $resource = TareaProgramadaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

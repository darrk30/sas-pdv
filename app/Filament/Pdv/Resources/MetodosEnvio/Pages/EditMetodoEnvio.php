<?php

namespace App\Filament\Pdv\Resources\MetodosEnvio\Pages;

use App\Filament\Pdv\Resources\MetodosEnvio\MetodoEnvioResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMetodoEnvio extends EditRecord
{
    protected static string $resource = MetodoEnvioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Pdv\Resources\Atributos\Pages;

use App\Filament\Pdv\Resources\Atributos\AtributoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAtributo extends EditRecord
{
    protected static string $resource = AtributoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

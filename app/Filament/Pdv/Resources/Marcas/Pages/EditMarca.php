<?php

namespace App\Filament\Pdv\Resources\Marcas\Pages;

use App\Filament\Pdv\Resources\Marcas\MarcaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMarca extends EditRecord
{
    protected static string $resource = MarcaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

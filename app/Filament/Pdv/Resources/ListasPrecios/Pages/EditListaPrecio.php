<?php

namespace App\Filament\Pdv\Resources\ListasPrecios\Pages;

use App\Filament\Pdv\Resources\ListasPrecios\ListaPrecioResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditListaPrecio extends EditRecord
{
    protected static string $resource = ListaPrecioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

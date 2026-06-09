<?php

namespace App\Filament\Pdv\Resources\Produccions\Pages;

use App\Filament\Pdv\Resources\Produccions\ProduccionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduccion extends EditRecord
{
    protected static string $resource = ProduccionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

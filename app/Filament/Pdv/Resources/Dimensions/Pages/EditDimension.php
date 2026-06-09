<?php

namespace App\Filament\Pdv\Resources\Dimensions\Pages;

use App\Filament\Pdv\Resources\Dimensions\DimensionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDimension extends EditRecord
{
    protected static string $resource = DimensionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

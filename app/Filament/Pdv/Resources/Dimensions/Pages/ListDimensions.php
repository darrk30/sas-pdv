<?php

namespace App\Filament\Pdv\Resources\Dimensions\Pages;

use App\Filament\Pdv\Resources\Dimensions\DimensionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDimensions extends ListRecords
{
    protected static string $resource = DimensionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

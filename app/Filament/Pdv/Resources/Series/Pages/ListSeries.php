<?php

namespace App\Filament\Pdv\Resources\Series\Pages;

use App\Filament\Pdv\Resources\Series\SerieResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeries extends ListRecords
{
    protected static string $resource = SerieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

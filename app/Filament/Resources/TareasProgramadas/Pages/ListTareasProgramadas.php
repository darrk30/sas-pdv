<?php

namespace App\Filament\Resources\TareasProgramadas\Pages;

use App\Filament\Resources\TareasProgramadas\TareaProgramadaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTareasProgramadas extends ListRecords
{
    protected static string $resource = TareaProgramadaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

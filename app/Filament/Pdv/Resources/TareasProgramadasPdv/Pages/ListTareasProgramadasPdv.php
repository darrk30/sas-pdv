<?php

namespace App\Filament\Pdv\Resources\TareasProgramadasPdv\Pages;

use App\Filament\Pdv\Resources\TareasProgramadasPdv\TareaProgramadaPdvResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTareasProgramadasPdv extends ListRecords
{
    protected static string $resource = TareaProgramadaPdvResource::class;

    protected static string $pollingInterval = '5s';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

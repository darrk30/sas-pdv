<?php

namespace App\Filament\Pdv\Resources\GastosFijos\Pages;

use App\Filament\Pdv\Resources\GastosFijos\GastoFijoResource;
use App\Filament\Pdv\Widgets\GastosFijosMetaWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGastosFijos extends ListRecords
{
    protected static string $resource = GastoFijoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo gasto fijo'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            GastosFijosMetaWidget::class,
        ];
    }
}

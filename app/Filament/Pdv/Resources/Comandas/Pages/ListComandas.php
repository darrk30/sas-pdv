<?php

namespace App\Filament\Pdv\Resources\Comandas\Pages;

use App\Filament\Pdv\Resources\Comandas\ComandaResource;
use App\Filament\Pdv\Pages\MapaMesasPage;
use Filament\Resources\Pages\ListRecords;

class ListComandas extends ListRecords
{
    protected static string $resource = ComandaResource::class;

    // Redirigir siempre al mapa de mesas (esta lista no se usa directamente)
    public function mount(): void
    {
        $this->redirect(MapaMesasPage::getUrl(tenant: filament()->getTenant()));
    }
}

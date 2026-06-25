<?php

namespace App\Filament\Pdv\Resources\Promociones\Pages;

use App\Filament\Pdv\Resources\Promociones\PromocionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromocion extends CreateRecord
{
    protected static string $resource = PromocionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

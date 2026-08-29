<?php

namespace App\Filament\Resources\Anuncios\Pages;

use App\Filament\Resources\Anuncios\AnuncioResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnuncio extends CreateRecord
{
    protected static string $resource = AnuncioResource::class;
}

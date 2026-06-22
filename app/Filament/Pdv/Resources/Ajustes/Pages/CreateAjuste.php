<?php

namespace App\Filament\Pdv\Resources\Ajustes\Pages;

use App\Filament\Pdv\Resources\Ajustes\AjusteResource;
use App\Models\Producto;
use App\Models\UnidadesMedida;
use App\Models\Variante;
use App\Services\InventarioCoreService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use InvalidArgumentException;
use RuntimeException;

class CreateAjuste extends CreateRecord
{
    protected static string $resource = AjusteResource::class;

}

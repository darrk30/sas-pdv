<?php

namespace App\Filament\Pdv\Resources\Users\Pages;

use App\Filament\Pdv\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->hidden(function (): bool {
                    $empresa = Filament::getTenant();
                    $plan    = $empresa?->suscripcion?->plan;
                    if (! $plan) return false;
                    return $empresa->usuarios()->count() >= $plan->maximo_usuarios;
                }),
        ];
    }
}

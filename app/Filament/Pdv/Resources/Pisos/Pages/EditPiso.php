<?php

namespace App\Filament\Pdv\Resources\Pisos\Pages;

use App\Filament\Pdv\Resources\Pisos\PisoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPiso extends EditRecord
{
    protected static string $resource = PisoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn () => $this->record->mesas()->exists()),
        ];
    }
}

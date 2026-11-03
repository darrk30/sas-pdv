<?php

namespace App\Filament\Pdv\Resources\Ajustes\Pages;

use App\Filament\Pdv\Resources\Ajustes\AjusteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAjuste extends EditRecord
{
    protected static string $resource = AjusteResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // Solo los ajustes en 'borrador' son editables (el EditAction de la tabla
    // está oculto para cualquier otro estado). Como el borrador nunca ha
    // aplicado stock, no hay que revertir ni reaplicar nada al editar.

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->estado === 'borrador'),
        ];
    }
}

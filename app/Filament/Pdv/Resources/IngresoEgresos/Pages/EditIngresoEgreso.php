<?php

namespace App\Filament\Pdv\Resources\IngresoEgresos\Pages;

use App\Filament\Pdv\Resources\IngresoEgresos\IngresoEgresoResource;
use Filament\Resources\Pages\EditRecord;

// Esta página existe solo para que la ruta no rompa si se accede directamente,
// pero en el resource no se enlaza ningún EditAction — los movimientos son inmutables.
class EditIngresoEgreso extends EditRecord
{
    protected static string $resource = IngresoEgresoResource::class;

    public function mount(int|string $record): void
    {
        $this->redirect(IngresoEgresoResource::getUrl('index'));
    }
}

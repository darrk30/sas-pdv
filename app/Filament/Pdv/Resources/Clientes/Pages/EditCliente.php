<?php

namespace App\Filament\Pdv\Resources\Clientes\Pages;

use App\Filament\Pdv\Resources\Clientes\ClienteResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCliente extends EditRecord
{
    protected static string $resource = ClienteResource::class;

    protected function afterFill(): void
    {
        if ($this->getRecord()->numero_documento === '99999999') {
            Notification::make()
                ->warning()
                ->title('El cliente por defecto no se puede editar.')
                ->send();

            $this->redirect(ClienteResource::getUrl('index'));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->hidden(fn() => $this->getRecord()->numero_documento === '99999999'),
        ];
    }
}

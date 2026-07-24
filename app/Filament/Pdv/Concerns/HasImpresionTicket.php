<?php

namespace App\Filament\Pdv\Concerns;

use Filament\Actions\Action;

trait HasImpresionTicket
{
    public function imprimirTicket(int $id): void
    {
        $this->dispatch('pdv-imprimir-ticket', url: route('pdv.ticket.venta', $id));
    }

    public function buildImprimirTicketAction(): Action
    {
        return Action::make('ticket')
            ->label('Imprimir ticket')
            ->icon('heroicon-o-printer')
            ->action(fn (\App\Models\Venta $record) => $this->imprimirTicket($record->id));
    }
}

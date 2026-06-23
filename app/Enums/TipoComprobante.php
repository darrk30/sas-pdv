<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TipoComprobante: string implements HasLabel, HasColor, HasIcon
{
    case Factura        = 'factura';
    case Boleta         = 'boleta';
    case Ticket         = 'ticket';
    case SinComprobante = 'sin_comprobante';
    case NotaCredito    = 'nota_credito';
    case NotaDebito     = 'nota_debito';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Factura        => 'Factura',
            self::Boleta         => 'Boleta',
            self::Ticket         => 'Ticket',
            self::SinComprobante => 'Sin Comprobante',
            self::NotaCredito    => 'Nota de Crédito',
            self::NotaDebito     => 'Nota de Débito',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Factura        => 'info',
            self::Boleta         => 'success',
            self::Ticket         => 'warning',
            self::SinComprobante => 'gray',
            self::NotaCredito    => 'success',
            self::NotaDebito     => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Factura        => 'heroicon-o-document-text',
            self::Boleta         => 'heroicon-o-receipt-percent',
            self::Ticket         => 'heroicon-o-ticket',
            self::SinComprobante => 'heroicon-o-no-symbol',
            self::NotaCredito    => 'heroicon-o-arrow-down-circle',
            self::NotaDebito     => 'heroicon-o-arrow-up-circle',
        };
    }
}

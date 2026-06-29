<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EstadoOrden: string implements HasLabel, HasColor, HasIcon
{
    case PendientePago   = 'pendiente_pago';
    case PagoConfirmado  = 'pago_confirmado';
    case Cancelada       = 'cancelada';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendientePago  => 'Pendiente de pago',
            self::PagoConfirmado => 'Pago confirmado',
            self::Cancelada      => 'Cancelada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PendientePago  => 'warning',
            self::PagoConfirmado => 'success',
            self::Cancelada      => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PendientePago  => 'heroicon-o-banknotes',
            self::PagoConfirmado => 'heroicon-o-check-badge',
            self::Cancelada      => 'heroicon-o-x-circle',
        };
    }

    public function esFinal(): bool
    {
        return in_array($this, [self::PagoConfirmado, self::Cancelada]);
    }
}

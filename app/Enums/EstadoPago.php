<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EstadoPago: string implements HasLabel, HasColor, HasIcon
{
    case Pendiente = 'pendiente';
    case Parcial   = 'parcial';
    case Pagado    = 'pagado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Parcial   => 'Parcial',
            self::Pagado    => 'Pagado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pendiente => 'warning',
            self::Parcial   => 'info',
            self::Pagado    => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pendiente => 'heroicon-o-clock',
            self::Parcial   => 'heroicon-o-banknotes',
            self::Pagado    => 'heroicon-o-check-circle',
        };
    }
}

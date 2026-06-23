<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EstadoPago: string implements HasLabel, HasColor, HasIcon
{
    case Pendiente = 'pendiente';
    case Pagado    = 'pagado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Pagado    => 'Pagado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pendiente => 'warning',
            self::Pagado    => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pendiente => 'heroicon-o-clock',
            self::Pagado    => 'heroicon-o-banknotes',
        };
    }
}

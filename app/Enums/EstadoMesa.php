<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EstadoMesa: string implements HasLabel, HasColor, HasIcon
{
    case Libre    = 'libre';
    case Ocupada  = 'ocupada';
    case Pagando  = 'pagando';

    public function getLabel(): string
    {
        return match ($this) {
            self::Libre   => 'Libre',
            self::Ocupada => 'Ocupada',
            self::Pagando => 'Pagando',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Libre   => 'success',
            self::Ocupada => 'danger',
            self::Pagando => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Libre   => 'heroicon-o-check-circle',
            self::Ocupada => 'heroicon-o-user-group',
            self::Pagando => 'heroicon-o-banknotes',
        };
    }
}

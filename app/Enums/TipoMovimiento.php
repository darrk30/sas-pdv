<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TipoMovimiento: string implements HasLabel, HasColor, HasIcon
{
    case Ingreso = 'ingreso';
    case Egreso  = 'egreso';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Ingreso => 'Ingreso',
            self::Egreso  => 'Egreso',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Ingreso => 'success',
            self::Egreso  => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Ingreso => 'heroicon-o-arrow-down-circle',
            self::Egreso  => 'heroicon-o-arrow-up-circle',
        };
    }
}

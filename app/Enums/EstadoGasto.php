<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EstadoGasto: string implements HasLabel, HasColor, HasIcon
{
    case Pendiente = 'pendiente';
    case Aprobado  = 'aprobado';
    case Anulado   = 'anulado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Aprobado  => 'Aprobado',
            self::Anulado   => 'Anulado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pendiente => 'warning',
            self::Aprobado  => 'success',
            self::Anulado   => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pendiente => 'heroicon-o-clock',
            self::Aprobado  => 'heroicon-o-check-circle',
            self::Anulado   => 'heroicon-o-x-circle',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EstadoDespacho: string implements HasLabel, HasColor, HasIcon
{
    case Pendiente = 'pendiente';
    case Recibido  = 'recibido';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Recibido  => 'Recibido',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pendiente => 'warning',
            self::Recibido  => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pendiente => 'heroicon-o-clock',
            self::Recibido  => 'heroicon-o-check-circle',
        };
    }
}

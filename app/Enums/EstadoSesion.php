<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EstadoSesion: string implements HasLabel, HasColor, HasIcon
{
    case Abierta = 'abierta';
    case Cerrada = 'cerrada';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Abierta => 'Abierta',
            self::Cerrada => 'Cerrada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Abierta => 'success',
            self::Cerrada => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Abierta => 'heroicon-o-lock-open',
            self::Cerrada => 'heroicon-o-lock-closed',
        };
    }
}

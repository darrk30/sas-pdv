<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EstadoPromocion: string implements HasLabel, HasColor, HasIcon
{
    case Activo    = 'activo';
    case Inactivo  = 'inactivo';
    case Archivado = 'archivado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Activo    => 'Activo',
            self::Inactivo  => 'Inactivo',
            self::Archivado => 'Archivado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Activo    => 'success',
            self::Inactivo  => 'warning',
            self::Archivado => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Activo    => 'heroicon-o-check-circle',
            self::Inactivo  => 'heroicon-o-pause-circle',
            self::Archivado => 'heroicon-o-archive-box',
        };
    }
}

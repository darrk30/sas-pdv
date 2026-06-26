<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EstadoVenta: string implements HasLabel, HasColor, HasIcon
{
    case Borrador       = 'borrador';
    case Completada     = 'completada';
    case Anulada        = 'anulada';
    case PendienteEnvio = 'pendiente_envio';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Borrador       => 'Borrador',
            self::Completada     => 'Completada',
            self::Anulada        => 'Anulada',
            self::PendienteEnvio => 'Pendiente de envío',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Borrador       => 'gray',
            self::Completada     => 'success',
            self::Anulada        => 'danger',
            self::PendienteEnvio => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Borrador       => 'heroicon-o-pencil',
            self::Completada     => 'heroicon-o-check-circle',
            self::Anulada        => 'heroicon-o-x-circle',
            self::PendienteEnvio => 'heroicon-o-truck',
        };
    }
}

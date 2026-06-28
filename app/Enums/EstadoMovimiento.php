<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EstadoMovimiento: string implements HasLabel, HasColor, HasIcon
{
    case Aprobado  = 'aprobado';
    case Anulado   = 'anulado';
    case PorCobrar = 'por_cobrar';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Aprobado  => 'Aprobado',
            self::Anulado   => 'Anulado',
            self::PorCobrar => 'Por cobrar',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Aprobado  => 'success',
            self::Anulado   => 'danger',
            self::PorCobrar => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Aprobado  => 'heroicon-o-check-circle',
            self::Anulado   => 'heroicon-o-x-circle',
            self::PorCobrar => 'heroicon-o-clock',
        };
    }
}

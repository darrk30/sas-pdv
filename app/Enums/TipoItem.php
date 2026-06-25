<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TipoItem: string implements HasLabel, HasColor, HasIcon
{
    case Producto  = 'producto';
    case Variante  = 'variante';
    case Promocion = 'promocion';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Producto  => 'Producto',
            self::Variante  => 'Variante',
            self::Promocion => 'Promoción',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Producto  => 'info',
            self::Variante  => 'primary',
            self::Promocion => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Producto  => 'heroicon-o-cube',
            self::Variante  => 'heroicon-o-squares-2x2',
            self::Promocion => 'heroicon-o-tag',
        };
    }
}

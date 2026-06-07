<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum MetodoPago: string implements HasLabel, HasColor, HasIcon
{
    case Transferencia = 'transferencia';
    case Yape = 'yape';
    case Plin = 'plin';
    case Tarjeta = 'tarjeta';
    case Efectivo = 'efectivo';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Transferencia => 'Transferencia Bancaria',
            self::Yape => 'Yape',
            self::Plin => 'Plin',
            self::Tarjeta => 'Tarjeta de Crédito / Débito',
            self::Efectivo => 'Efectivo',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Transferencia => 'info',     // Azul
            self::Yape => 'purple',            // Morado (típico de Yape)
            self::Plin => 'info',              // Celeste/Azul
            self::Tarjeta => 'warning',        // Amarillo/Naranja
            self::Efectivo => 'success',       // Verde
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Transferencia => 'heroicon-m-building-library',
            self::Yape => 'heroicon-m-device-phone-mobile',
            self::Plin => 'heroicon-m-qr-code',
            self::Tarjeta => 'heroicon-m-credit-card',
            self::Efectivo => 'heroicon-m-banknotes',
        };
    }
}
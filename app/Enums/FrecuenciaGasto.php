<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum FrecuenciaGasto: string implements HasLabel, HasColor, HasIcon
{
    case Diario    = 'diario';
    case Semanal   = 'semanal';
    case Quincenal = 'quincenal';
    case Mensual   = 'mensual';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Diario    => 'Diario',
            self::Semanal   => 'Semanal',
            self::Quincenal => 'Quincenal',
            self::Mensual   => 'Mensual',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Diario    => 'info',
            self::Semanal   => 'primary',
            self::Quincenal => 'warning',
            self::Mensual   => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Diario    => 'heroicon-o-sun',
            self::Semanal   => 'heroicon-o-calendar-days',
            self::Quincenal => 'heroicon-o-calendar',
            self::Mensual   => 'heroicon-o-calendar',
        };
    }

    /** Cuántas veces ocurre este gasto por mes (factor de normalización). */
    public function vecesAlMes(): float
    {
        return match ($this) {
            self::Diario    => 30.0,
            self::Semanal   => 4.333,
            self::Quincenal => 2.0,
            self::Mensual   => 1.0,
        };
    }

    /**
     * Monto de este gasto normalizado al período de vista indicado.
     * Ej: gasto semanal S/50 visto en período mensual → S/50 × 4.333 = S/216.67
     */
    public function montoEnPeriodo(float $monto, self $periodoVista): float
    {
        // Convertir a mensual primero, luego al período de vista
        return ($monto * $this->vecesAlMes()) / $periodoVista->vecesAlMes();
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EstadoSunat: string implements HasLabel, HasColor, HasIcon
{
    case NoAplica    = 'no_aplica';
    case PorEnviar   = 'por_enviar';
    case Pendiente   = 'pendiente';
    case Enviado     = 'enviado';
    case EnResumen   = 'en_resumen';
    case Aceptado    = 'aceptado';
    case Observado   = 'observado';
    case Rechazado   = 'rechazado';
    case PorDarBaja  = 'por_dar_baja';
    case DadoDeBaja  = 'dado_de_baja';
    case Error       = 'error';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NoAplica   => 'No aplica',
            self::PorEnviar  => 'Por enviar',
            self::Pendiente  => 'Pendiente',
            self::Enviado    => 'Enviado',
            self::EnResumen  => 'En resumen',
            self::Aceptado   => 'Aceptado',
            self::Observado  => 'Observado',
            self::Rechazado  => 'Rechazado',
            self::PorDarBaja => 'Por dar de baja',
            self::DadoDeBaja => 'Dado de baja',
            self::Error      => 'Error',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NoAplica   => 'gray',
            self::PorEnviar  => 'info',
            self::Pendiente  => 'warning',
            self::Enviado    => 'info',
            self::EnResumen  => 'warning',
            self::Aceptado   => 'success',
            self::Observado  => 'warning',
            self::Rechazado  => 'danger',
            self::PorDarBaja => 'warning',
            self::DadoDeBaja => 'gray',
            self::Error      => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::NoAplica   => 'heroicon-o-minus-circle',
            self::PorEnviar  => 'heroicon-o-paper-airplane',
            self::Pendiente  => 'heroicon-o-clock',
            self::Enviado    => 'heroicon-o-arrow-up-circle',
            self::EnResumen  => 'heroicon-o-archive-box',
            self::Aceptado   => 'heroicon-o-check-circle',
            self::Observado  => 'heroicon-o-exclamation-circle',
            self::Rechazado  => 'heroicon-o-x-circle',
            self::PorDarBaja => 'heroicon-o-trash',
            self::DadoDeBaja => 'heroicon-o-x-mark',
            self::Error      => 'heroicon-o-exclamation-triangle',
        };
    }
}

<?php

namespace App\Filament\Pdv\Widgets;

use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class BienvenidaWidget extends Widget
{
    protected string $view = 'filament.pdv.widgets.bienvenida';
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    public string $nombreUsuario = '';
    public string $nombreEmpresa = '';
    public string $fechaHoy      = '';

    public function mount(): void
    {
        $this->nombreUsuario = auth()->user()?->name ?? 'Usuario';
        $this->nombreEmpresa = Filament::getTenant()?->name ?? '';
        $this->fechaHoy      = now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY');
    }
}

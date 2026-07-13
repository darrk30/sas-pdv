<?php

namespace App\Filament\Pdv\Concerns;

trait HasFullWidthPage
{
    public function getHeading(): string { return ''; }
    public function getMaxContentWidth(): ?string { return 'full'; }
}

<?php

namespace App\Filament\Pdv\Resources\Gastos\Pages;

use App\Filament\Pdv\Resources\Gastos\GastoResource;
use App\Filament\Pdv\Widgets\GastosStatsWidget;
use App\Services\GastoExportService;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListGastos extends ListRecords
{
    protected static string $resource = GastoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('exportar_excel')
                ->label('Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(function (): StreamedResponse {
                    return app(GastoExportService::class)->exportarExcel(Filament::getTenant());
                }),

            Action::make('exportar_pdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(function (): StreamedResponse {
                    return app(GastoExportService::class)->exportarPdf(Filament::getTenant());
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            GastosStatsWidget::class,
        ];
    }
}

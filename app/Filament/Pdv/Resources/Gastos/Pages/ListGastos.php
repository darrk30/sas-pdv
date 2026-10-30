<?php

namespace App\Filament\Pdv\Resources\Gastos\Pages;

use App\Filament\Pdv\Pages\ReporteDescargasPage;
use App\Filament\Pdv\Resources\Gastos\GastoResource;
use App\Filament\Pdv\Widgets\GastosStatsWidget;
use App\Jobs\GenerarReporteJob;
use App\Models\Gasto;
use App\Models\ReporteDescarga;
use App\Services\GastoExportService;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListGastos extends ListRecords
{
    protected static string $resource = GastoResource::class;

    private const UMBRAL_COLA_EXCEL = 1000;
    private const UMBRAL_COLA_PDF   = 1000;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('exportar_excel')
                ->label('Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(fn() => $this->despacharReporte('excel')),

            Action::make('exportar_pdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn() => $this->despacharReporte('pdf')),
        ];
    }

    private function despacharReporte(string $tipo): mixed
    {
        $umbral = $tipo === 'pdf' ? self::UMBRAL_COLA_PDF : self::UMBRAL_COLA_EXCEL;
        $count  = Gasto::where('empresa_id', Filament::getTenant()->id)->count();

        if ($count <= $umbral) {
            $service = app(GastoExportService::class);

            return $tipo === 'pdf'
                ? $service->exportarPdf(Filament::getTenant())
                : $service->exportarExcel(Filament::getTenant());
        }

        $reporte = ReporteDescarga::create([
            'empresa_id' => Filament::getTenant()->id,
            'user_id'    => auth()->id(),
            'tipo'       => $tipo,
            'reporte'    => 'gastos',
            'estado'     => 'pendiente',
            'filtros'    => [],
        ]);

        GenerarReporteJob::dispatch($reporte->id);

        \Filament\Notifications\Notification::make()
            ->title('Reporte en proceso')
            ->body("Son {$count} gastos, se genera en segundo plano. Recibirás una notificación cuando esté listo.")
            ->info()
            ->actions([
                Action::make('ver_descargas')
                    ->label('Ver descargas')
                    ->url(ReporteDescargasPage::getUrl(panel: 'pdv', tenant: Filament::getTenant()))
                    ->button(),
            ])
            ->send();

        return null;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            GastosStatsWidget::class,
        ];
    }
}

<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoSesion;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use App\Models\SesionCaja;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CierresCajaPage extends Page implements HasTable
{
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.cierres-caja';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';
    protected static ?string $navigationLabel = 'Cierres de Caja';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 5;
    protected static ?string $title = 'Cierres de Cajas';

    public function getHeading(): string { return 'Cierres de Cajas'; }

    public static function canAccess(): bool
    {
        return Filament::getTenant()->tieneModulo('cierres_caja')
            && (auth()->user()?->can('caja.cierres') ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => SesionCaja::where('empresa_id', Filament::getTenant()->id)
                ->forCurrentUser()
                ->with(['caja', 'cajero:id,name'])
                ->withCount(['pagos as tiene_cuadre' => fn($q) => $q->whereNotNull('importe_cajero')])
            )
            ->columns([
                TextColumn::make('caja.nombre')
                    ->label('Caja')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('cajero.name')
                    ->label('Cajero')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('fecha_apertura')
                    ->label('Apertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('fecha_cierre')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('En curso')
                    ->sortable(),

                TextColumn::make('duracion')
                    ->label('Duración')
                    ->state(fn(SesionCaja $record): string => $record->fecha_cierre
                        ? $record->fecha_apertura->diffForHumans($record->fecha_cierre, true, false, 2)
                        : '—')
                    ->color('gray'),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),

                IconColumn::make('tiene_cuadre')
                    ->label('Cuadre')
                    ->state(fn(SesionCaja $record): bool => $record->tiene_cuadre > 0)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->trueColor('success')
                    ->falseIcon(''),

                TextColumn::make('total_sistema')
                    ->label('Total sistema')
                    ->money('PEN')
                    ->placeholder('—')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(EstadoSesion::class),

                Filter::make('apertura')
                    ->label('Fecha de apertura')
                    ->form([
                        DatePicker::make('desde')->label('Apertura desde')->displayFormat('d/m/Y'),
                        DatePicker::make('hasta')->label('Apertura hasta')->displayFormat('d/m/Y'),
                    ])
                    ->query(fn(Builder $query, array $data) => $query
                        ->when($data['desde'] ?? null, fn($q, $v) => $q->whereDate('fecha_apertura', '>=', $v))
                        ->when($data['hasta'] ?? null, fn($q, $v) => $q->whereDate('fecha_apertura', '<=', $v)))
                    ->indicateUsing(function (array $data): array {
                        $i = [];
                        if ($data['desde'] ?? null) $i[] = 'Apertura desde ' . $data['desde'];
                        if ($data['hasta'] ?? null) $i[] = 'Apertura hasta ' . $data['hasta'];
                        return $i;
                    }),

                Filter::make('cierre')
                    ->label('Fecha de cierre')
                    ->form([
                        DatePicker::make('desde')->label('Cierre desde')->displayFormat('d/m/Y'),
                        DatePicker::make('hasta')->label('Cierre hasta')->displayFormat('d/m/Y'),
                    ])
                    ->query(fn(Builder $query, array $data) => $query
                        ->when($data['desde'] ?? null, fn($q, $v) => $q->whereDate('fecha_cierre', '>=', $v))
                        ->when($data['hasta'] ?? null, fn($q, $v) => $q->whereDate('fecha_cierre', '<=', $v)))
                    ->indicateUsing(function (array $data): array {
                        $i = [];
                        if ($data['desde'] ?? null) $i[] = 'Cierre desde ' . $data['desde'];
                        if ($data['hasta'] ?? null) $i[] = 'Cierre hasta ' . $data['hasta'];
                        return $i;
                    }),
            ])
            ->recordActions([
                Action::make('ver_reporte')
                    ->label('Ver reporte')
                    ->icon('heroicon-o-chart-bar-square')
                    ->color('primary')
                    ->url(fn(SesionCaja $record) => ReporteSesionPage::getUrl() . '?sesionId=' . $record->id)
                    ->hidden(fn(SesionCaja $record) => $record->estaAbierta()),
            ])
            ->defaultSort('fecha_apertura', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

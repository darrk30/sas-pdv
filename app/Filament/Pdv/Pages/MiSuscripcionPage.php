<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\MetodoPago;
use App\Models\PagosCliente;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use UnitEnum;

class MiSuscripcionPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-credit-card';
    protected static ?string                $navigationLabel = 'Mi Suscripción';
    protected static ?string                $title           = 'Mi Suscripción';
    protected static string|UnitEnum|null   $navigationGroup = 'Configuración';
    protected static ?int                   $navigationSort  = 2;
    protected string                        $view            = 'filament.pdv.pages.mi-suscripcion';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('config.suscripcion') ?? false;
    }

    #[Computed]
    public function suscripcion()
    {
        return Filament::getTenant()->suscripcion()->with('plan')->first();
    }

    #[Computed]
    public function pagos()
    {
        return Filament::getTenant()->pagos()->orderByDesc('fecha_pago')->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->registrarPagoAction(),
        ];
    }

    public function registrarPagoAction(): Action
    {
        $suscripcion = $this->suscripcion;

        return Action::make('registrarPago')
            ->label('Registrar comprobante de pago')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->disabled(fn () => ! $suscripcion)
            ->tooltip(fn () => ! $suscripcion ? 'No tienes suscripción activa' : null)
            ->modalHeading('Registrar comprobante de pago')
            ->modalDescription('Sube el comprobante de tu pago para que el administrador lo verifique y renueve tu suscripción.')
            ->modalWidth('lg')
            ->schema([
                TextInput::make('monto')
                    ->label('Monto pagado')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->prefix('S/'),

                Select::make('metodo_pago')
                    ->label('Método de Pago')
                    ->native(false)
                    ->options(MetodoPago::class)
                    ->default(MetodoPago::Transferencia->value)
                    ->required(),

                TextInput::make('referencia')
                    ->label('N° de Operación / Referencia')
                    ->maxLength(255),

                DateTimePicker::make('fecha_pago')
                    ->label('Fecha del Pago')
                    ->default(now())
                    ->required()
                    ->native(false),

                FileUpload::make('path_url')
                    ->label('Comprobante (captura o voucher)')
                    ->image()
                    ->disk('public')
                    ->directory('comprobantes')
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) use ($suscripcion): void {
                if (! $suscripcion) {
                    Notification::make()
                        ->warning()
                        ->title('Sin suscripción activa')
                        ->body('Tu empresa aún no tiene un plan asignado. Contacta al administrador.')
                        ->send();
                    return;
                }

                PagosCliente::create([
                    'suscripcion_id' => $suscripcion->id,
                    'monto'          => $data['monto'],
                    'metodo_pago'    => $data['metodo_pago'],
                    'referencia'     => $data['referencia'] ?? null,
                    'fecha_pago'     => $data['fecha_pago'],
                    'path_url'       => $data['path_url'] ?? null,
                ]);

                Notification::make()
                    ->success()
                    ->title('Pago registrado correctamente')
                    ->body('Tu comprobante fue enviado. El administrador lo revisará y renovará tu suscripción pronto.')
                    ->send();
            });
    }
}

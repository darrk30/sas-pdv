<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\MetodoPago;
use App\Models\PagosCliente;
use App\Models\Plan;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use UnitEnum;

class MiSuscripcionPage extends Page implements HasTable
{
    use InteractsWithTable;

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
    public function tienePagoPendiente(): bool
    {
        return Filament::getTenant()
            ->pagos()
            ->where('pagos_clientes.estado', 'pendiente')
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PagosCliente::query()
                    ->whereIn('suscripcion_id', function ($q) {
                        $q->select('id')
                          ->from('suscripciones')
                          ->where('empresa_id', Filament::getTenant()->id);
                    })
                    ->orderByDesc('fecha_pago')
            )
            ->heading('Historial de pagos registrados')
            ->columns([
                TextColumn::make('fecha_pago')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('monto')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('ciclo')
                    ->label('Ciclo')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'anual'  => 'Anual',
                        'prueba' => 'Prueba',
                        default  => 'Mensual',
                    }),

                TextColumn::make('periodo_desde')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                TextColumn::make('periodo_hasta')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->placeholder('—'),

                TextColumn::make('metodo_pago')
                    ->label('Método')
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('concepto')
                    ->label('Concepto')
                    ->getStateUsing(fn ($record) => $record->concepto ?? $record->referencia)
                    ->placeholder('—'),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'aprobado'  => 'success',
                        'rechazado' => 'danger',
                        default     => 'warning',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'aprobado'  => 'Aprobado',
                        'rechazado' => 'Rechazado',
                        default     => 'Pendiente',
                    }),

                TextColumn::make('path_url')
                    ->label('Comprobante')
                    ->formatStateUsing(fn ($state) => $state ? 'Ver' : '—')
                    ->url(fn ($record) => $record->path_url ? asset('storage/' . $record->path_url) : null)
                    ->openUrlInNewTab()
                    ->color(fn ($record) => $record->path_url ? 'primary' : null),
            ])
            ->emptyStateHeading('Sin pagos registrados')
            ->emptyStateDescription('Usa el botón "Registrar comprobante de pago" cuando realices tu próximo pago.')
            ->emptyStateIcon('heroicon-o-credit-card')
            ->paginated(false);
    }

    protected function getHeaderActions(): array
    {
        return [$this->registrarPagoAction()];
    }

    public function registrarPagoAction(): Action
    {
        return Action::make('registrarPago')
            ->label('Registrar comprobante de pago')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->visible(function () {
                $empresa     = Filament::getTenant()->fresh();
                $suscripcion = $empresa->suscripcion;

                // Mostrar si: en prueba, próxima a vencer, o inactiva — y sin pago pendiente
                $debeVerBoton = $suscripcion?->es_prueba_gratuita
                    || $empresa->suscripcion_proxima_a_vencer
                    || $empresa->estado === 'inactivo';

                return $debeVerBoton && ! $this->tienePagoPendiente;
            })
            ->disabled(fn () => ! $this->suscripcion)
            ->tooltip(fn () => ! $this->suscripcion ? 'No tienes suscripción activa' : null)
            ->modalHeading('Registrar comprobante de pago')
            ->modalDescription('Sube el comprobante de tu pago para que el administrador lo verifique y renueve tu suscripción.')
            ->modalWidth('lg')
            ->schema(function () {
                $suscripcion = $this->suscripcion;
                $plan        = $suscripcion?->plan;
                $tieneAnual  = $plan && $plan->precio_anual !== null && $plan->precio_anual > 0;

                $fields = [];

                // Selector de ciclo solo si el plan ofrece precio anual
                if ($tieneAnual) {
                    $fields[] = Radio::make('ciclo')
                        ->label('Ciclo de facturación')
                        ->options([
                            'mensual' => "Mensual — S/ {$plan->precio}",
                            'anual'   => "Anual — S/ {$plan->precio_anual} (ahorra " . round((1 - $plan->precio_anual / ($plan->precio * 12)) * 100) . '%)',
                        ])
                        ->default('mensual')
                        ->live()
                        ->afterStateUpdated(function ($state, $set) use ($plan) {
                            $monto = ($state === 'anual') ? $plan->precio_anual : $plan->precio;
                            $set('monto', $monto);
                        })
                        ->inline(false);
                } else {
                    $fields[] = \Filament\Forms\Components\Hidden::make('ciclo')
                        ->default('mensual');
                }

                $fields[] = TextInput::make('monto')
                    ->label('Monto a pagar')
                    ->default(fn () => $plan?->precio ?? 0)
                    ->numeric()
                    ->prefix('S/')
                    ->readOnly()
                    ->helperText('El monto corresponde al precio del plan seleccionado.');

                $fields[] = Select::make('metodo_pago')
                    ->label('Método de Pago')
                    ->native(false)
                    ->options(MetodoPago::class)
                    ->default(MetodoPago::Transferencia->value)
                    ->required();

                $fields[] = TextInput::make('referencia')
                    ->label('N° de Operación / Referencia')
                    ->maxLength(255);

                $fields[] = DateTimePicker::make('fecha_pago')
                    ->label('Fecha del Pago')
                    ->default(now())
                    ->required()
                    ->native(false);

                $fields[] = FileUpload::make('path_url')
                    ->label('Comprobante (captura o voucher)')
                    ->image()
                    ->disk('public')
                    ->directory('comprobantes')
                    ->columnSpanFull();

                return $fields;
            })
            ->action(function (array $data): void {
                $suscripcion = $this->suscripcion;

                if (! $suscripcion) {
                    Notification::make()->warning()->title('Sin suscripción activa')
                        ->body('Tu empresa aún no tiene un plan asignado. Contacta al administrador.')
                        ->send();
                    return;
                }

                $plan   = $suscripcion->plan;
                $ciclo  = $data['ciclo'] ?? 'mensual';
                $monto  = ($ciclo === 'anual' && $plan->precio_anual) ? (float) $plan->precio_anual : (float) $plan->precio;
                $montoEnviado = (float) $data['monto'];

                if (round($montoEnviado, 2) !== round($monto, 2)) {
                    Notification::make()->danger()->title('Monto inválido')
                        ->body('El monto fue modificado y no coincide con el precio del plan. La operación fue cancelada.')
                        ->send();
                    return;
                }

                PagosCliente::create([
                    'suscripcion_id' => $suscripcion->id,
                    'plan_id'        => $suscripcion->plan_id,
                    'ciclo'          => $ciclo,
                    'monto'          => $monto,
                    'metodo_pago'    => $data['metodo_pago'],
                    'referencia'     => $data['referencia'] ?? null,
                    'fecha_pago'     => $data['fecha_pago'],
                    'path_url'       => $data['path_url'] ?? null,
                    'estado'         => 'pendiente',
                ]);

                Notification::make()->success()->title('Pago registrado correctamente')
                    ->body('Tu comprobante fue enviado. El administrador lo revisará y renovará tu suscripción pronto.')
                    ->send();

                // Notificar super admins
                $empresa  = Filament::getTenant();
                $adminIds = DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereNull('model_has_roles.empresa_id')
                    ->where('roles.name', 'Super Administrador')
                    ->whereNull('roles.empresa_id')
                    ->pluck('model_has_roles.model_id');

                $cicloLabel = $ciclo === 'anual' ? 'anual' : 'mensual';
                foreach (User::whereIn('id', $adminIds)->get() as $admin) {
                    Notification::make()->warning()->title('Pago pendiente de aprobación')
                        ->body("La empresa **{$empresa->name}** registró un comprobante de pago ({$cicloLabel}). Revísalo para renovar su suscripción.")
                        ->sendToDatabase($admin);
                }

                $this->dispatch('$refresh');
            });
    }
}

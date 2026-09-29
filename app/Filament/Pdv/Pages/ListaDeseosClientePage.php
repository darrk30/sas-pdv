<?php

namespace App\Filament\Pdv\Pages;

use App\Filament\Pdv\Concerns\HasFullWidthPage;
use App\Filament\Pdv\Resources\Clientes\ClienteResource;
use App\Mail\ListaDeseosRecordatorio;
use App\Models\ListaDeseo;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Storage;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Url;

class ListaDeseosClientePage extends Page implements HasTable
{
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view             = 'filament.pdv.pages.lista-deseos-cliente';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title    = 'Lista de deseos';

    #[Url] public ?int    $clienteId       = null;
    #[Url] public ?string $clienteNombre   = null;
    #[Url] public ?string $clienteEmail    = null;
    #[Url] public ?string $clienteTelefono = null;

    public function getHeading(): string
    {
        return 'Lista de deseos — ' . ($this->clienteNombre ?? '');
    }

    public function getBreadcrumbs(): array
    {
        return [
            ClienteResource::getUrl('index') => 'Clientes',
            $this->clienteNombre ?? 'Lista de deseos',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('whatsapp')
                ->label('Enviar WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('success')
                ->visible(fn () => (bool) $this->clienteTelefono)
                ->url(fn () => $this->buildWhatsappUrl())
                ->openUrlInNewTab(),

            Action::make('enviar_correo')
                ->label('Enviar correo')
                ->icon('heroicon-o-envelope')
                ->color('info')
                ->visible(fn () => (bool) $this->clienteEmail)
                ->modalHeading('Enviar recordatorio por correo')
                ->modalDescription(fn () => "Se enviará a {$this->clienteEmail} junto con los productos de su lista de deseos.")
                ->modalSubmitActionLabel('Enviar correo')
                ->form([
                    RichEditor::make('mensaje')
                        ->label('Mensaje personalizado')
                        ->placeholder('Ej: Hola, te recordamos que tienes productos guardados. ¡Aprovecha el descuento de esta semana!')
                        ->toolbarButtons(['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'link'])
                        ->nullable(),
                ])
                ->action(fn (array $data) => $this->enviarCorreo($data['mensaje'] ?? null)),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ListaDeseo::query()
                ->where('empresa_id', Filament::getTenant()->id)
                ->where('user_id', $this->clienteId)
                ->with(['producto.galeriaProductos', 'variante.valores.valor'])
                ->orderByDesc('created_at')
            )
            ->columns([
                ImageColumn::make('imagen_url')
                    ->label('')
                    ->state(fn (ListaDeseo $r) => $this->resolverImagenUrl($r) ?? asset('img/placeholder-producto.svg'))
                    ->width(52)
                    ->height(52)
                    ->extraImgAttributes(['style' => 'object-fit:cover;border-radius:8px;']),

                TextColumn::make('producto.nombre')
                    ->label('Producto')
                    ->weight('semibold')
                    ->description(fn (ListaDeseo $r) => $r->variante
                        ? $r->variante->valores->map(fn ($pav) => $pav->valor?->nombre)->filter()->implode(' / ')
                        : null
                    ),

                TextColumn::make('cantidad')
                    ->label('Cant.')
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('En lista desde')
                    ->since()
                    ->description(fn (ListaDeseo $r) => $r->created_at->format('d/m/Y')),
            ])
            ->emptyStateIcon('heroicon-o-heart')
            ->emptyStateHeading('Sin productos en la lista')
            ->emptyStateDescription('Este cliente no tiene productos guardados en su lista de deseos.')
            ->paginated(false);
    }

    private function resolverImagenUrl(ListaDeseo $item): ?string
    {
        if ($item->variante?->imagen) {
            return Storage::url($item->variante->imagen);
        }
        if ($item->producto?->logo) {
            return Storage::url($item->producto->logo);
        }
        $primera = $item->producto?->galeriaProductos?->first();
        if ($primera?->imagen_path) {
            return Storage::url($primera->imagen_path);
        }
        return null;
    }

    private function buildWhatsappUrl(): string
    {
        $items = ListaDeseo::where('empresa_id', Filament::getTenant()->id)
            ->where('user_id', $this->clienteId)
            ->with('producto')
            ->get();

        $empresa = Filament::getTenant()->nombre;
        $mensaje = "Hola {$this->clienteNombre} 👋\n\n";
        $mensaje .= "Notamos que tienes estos productos guardados en tu lista de deseos:\n\n";

        foreach ($items as $item) {
            $mensaje .= "• {$item->producto?->nombre}\n";
        }

        $mensaje .= "\n¿Te gustaría hacer tu pedido? Estamos para ayudarte 😊\n— {$empresa}";

        $tel = preg_replace('/\D/', '', $this->clienteTelefono ?? '');
        if (strlen($tel) <= 9) {
            $tel = '51' . $tel;
        }

        return 'https://wa.me/' . $tel . '?text=' . rawurlencode($mensaje);
    }

    private function enviarCorreo(?string $mensaje = null): void
    {
        $items = ListaDeseo::where('empresa_id', Filament::getTenant()->id)
            ->where('user_id', $this->clienteId)
            ->with(['producto', 'variante.valores.valor'])
            ->get();

        $empresa = Filament::getTenant();

        try {
            Mail::to($this->clienteEmail)
                ->send(new ListaDeseosRecordatorio(
                    clienteNombre: $this->clienteNombre ?? 'Cliente',
                    items:         $items,
                    empresa:       $empresa,
                    mensaje:       $mensaje,
                ));

            Notification::make()
                ->title('Correo enviado')
                ->body("Recordatorio enviado a {$this->clienteEmail}")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al enviar correo')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}

<?php

namespace App\Livewire\Tienda;

use App\Enums\EstadoGeneral;
use App\Enums\TipoDocumento;
use App\Enums\TipoItem;
use App\Models\Carrito as CarritoModel;
use App\Models\CarritoItem;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Inventario;
use App\Models\MetodoEnvio;
use App\Models\MetodoPago;
use App\Models\Orden;
use App\Models\OrdenDetalle;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\Variante;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::tienda')]
#[Title('Mi carrito')]
class Carrito extends Component
{
    public int   $empresaId = 0;

    // ── Items de invitado (desde Alpine localStorage) ─────────────
    public array $guestItems  = [];
    public bool  $guestIniciado = false;

    // ── Formulario de orden ───────────────────────────────────────
    public bool   $mostrarFormOrden = false;

    public string $chkNombre       = '';
    public string $chkApellidos    = '';
    public string $chkTipoDoc      = 'dni';
    public string $chkNumDoc       = '';
    public string $chkTelefono     = '';
    public string $chkEmail        = '';
    public string $chkDepartamento = '';
    public string $chkProvincia    = '';
    public string $chkDistrito     = '';
    public ?int   $chkMetodoEnvioId = null;
    public string $chkDireccion    = '';
    public ?int   $chkMetodoPagoId  = null;

    // ── Modal de éxito ────────────────────────────────────────────
    public bool   $mostrarModalExito  = false;
    public bool   $esOrdenGuest       = false;
    public string $ordenCodigo        = '';
    public float  $ordenTotal         = 0;
    public string $whatsappUrl        = '';

    protected function rules(): array
    {
        $metodo            = $this->chkMetodoEnvioId ? MetodoEnvio::find($this->chkMetodoEnvioId) : null;
        $requiereDireccion = $metodo?->con_direccion ?? false;

        return [
            'chkNombre'        => 'required|string|min:2',
            'chkApellidos'     => 'required|string|min:2',
            'chkTipoDoc'       => 'required|in:dni',
            'chkNumDoc'        => 'required|string|min:8|max:8',
            'chkTelefono'      => 'required|string|min:9|max:9',
            'chkEmail'         => 'nullable|email',
            'chkDepartamento'  => $requiereDireccion ? 'required|string|min:2' : 'nullable|string',
            'chkProvincia'     => $requiereDireccion ? 'required|string|min:2' : 'nullable|string',
            'chkDistrito'      => $requiereDireccion ? 'required|string|min:2' : 'nullable|string',
            'chkMetodoEnvioId' => 'required|integer',
            'chkDireccion'     => $requiereDireccion ? 'required|string|min:5' : 'nullable|string',
            'chkMetodoPagoId'  => 'required|integer',
        ];
    }

    protected function messages(): array
    {
        return [
            'chkNombre.required'        => 'El nombre es obligatorio.',
            'chkNombre.min'             => 'Ingresa al menos 2 caracteres.',
            'chkApellidos.required'     => 'Los apellidos son obligatorios.',
            'chkApellidos.min'          => 'Ingresa al menos 2 caracteres.',
            'chkNumDoc.required'        => 'El DNI es obligatorio.',
            'chkNumDoc.min'             => 'El DNI debe tener 8 dígitos.',
            'chkNumDoc.max'             => 'El DNI debe tener 8 dígitos.',
            'chkTelefono.required'      => 'El teléfono es obligatorio.',
            'chkTelefono.min'           => 'Ingresa un número válido de 9 dígitos.',
            'chkTelefono.max'           => 'Ingresa un número válido de 9 dígitos.',
            'chkMetodoEnvioId.required'  => 'Selecciona un método de envío.',
            'chkDireccion.required'      => 'La dirección de la agencia es obligatoria.',
            'chkDireccion.min'           => 'Ingresa una dirección completa.',
            'chkDepartamento.required'   => 'El departamento es obligatorio.',
            'chkProvincia.required'      => 'La provincia es obligatoria.',
            'chkDistrito.required'       => 'El distrito es obligatorio.',
            'chkMetodoPagoId.required'  => 'Selecciona un método de pago.',
        ];
    }

    public function mount(): void
    {
        $this->empresaId = app('tienda.empresa')->id;
    }

    // ── Recibir ítems de invitado desde Alpine ────────────────────

    #[On('browser:carrito-guest-items')]
    public function recibirItemsGuest(array $items): void
    {
        $this->guestItems = array_values(array_filter($items, fn($i) =>
            (!empty($i['producto_id']) || !empty($i['promocion_id'])) &&
            ($i['cantidad'] ?? 0) > 0
        ));
        $this->guestIniciado = true;
    }

    // ── Acciones del carrito (solo usuarios logueados) ────────────

    public function incrementar(int $itemId): void
    {
        $this->itemDelUsuario($itemId)?->increment('cantidad');
        $this->actualizarBadge();
    }

    public function decrementar(int $itemId): void
    {
        $item = $this->itemDelUsuario($itemId);
        if (! $item) return;
        $item->cantidad <= 1 ? $item->delete() : $item->decrement('cantidad');
        $this->actualizarBadge();
    }

    public function eliminarItem(int $itemId): void
    {
        $item = $this->itemDelUsuario($itemId);
        if (! $item) return;
        $nombre = $item->promocion?->nombre ?? $item->producto?->nombre ?? 'Producto';
        $item->delete();
        $this->actualizarBadge();
        $this->dispatch('toast', mensaje: "\"$nombre\" eliminado del carrito", tipo: 'info');
    }

    public function vaciarCarrito(): void
    {
        $userId  = Auth::guard('cliente')->id();
        $carrito = CarritoModel::where('empresa_id', $this->empresaId)
            ->where('user_id', $userId)->first();
        $carrito?->items()->delete();
        $this->dispatch('carrito-count-actualizado', count: 0);
        $this->dispatch('toast', mensaje: 'Carrito vaciado', tipo: 'info');
    }

    public function vaciarCarritoGuest(): void
    {
        $this->guestItems   = [];
        $this->guestIniciado = true;
        $this->dispatch('carrito-limpiar-local');
        $this->dispatch('carrito-count-actualizado', count: 0);
        $this->dispatch('toast', mensaje: 'Carrito vaciado', tipo: 'info');
    }

    public function eliminarItemGuest(int $index): void
    {
        $items = array_values($this->guestItems);
        if (! isset($items[$index])) return;
        $nombre = $items[$index]['nombre'] ?? 'Producto';
        array_splice($items, $index, 1);
        $this->guestItems   = array_values($items);
        $this->guestIniciado = true;
        $total = array_sum(array_column($this->guestItems, 'cantidad'));
        $this->dispatch('carrito-guest-actualizado', items: $this->guestItems);
        $this->dispatch('carrito-count-actualizado', count: $total);
        $this->dispatch('toast', mensaje: "\"$nombre\" eliminado", tipo: 'info');
    }

    public function incrementarGuest(int $index): void
    {
        if (! isset($this->guestItems[$index])) return;
        $this->guestItems[$index]['cantidad']++;
        $total = array_sum(array_column($this->guestItems, 'cantidad'));
        $this->dispatch('carrito-guest-actualizado', items: $this->guestItems);
        $this->dispatch('carrito-count-actualizado', count: $total);
    }

    public function decrementarGuest(int $index): void
    {
        if (! isset($this->guestItems[$index])) return;
        if (($this->guestItems[$index]['cantidad'] ?? 1) <= 1) {
            $this->eliminarItemGuest($index);
            return;
        }
        $this->guestItems[$index]['cantidad']--;
        $total = array_sum(array_column($this->guestItems, 'cantidad'));
        $this->dispatch('carrito-guest-actualizado', items: $this->guestItems);
        $this->dispatch('carrito-count-actualizado', count: $total);
    }

    // ── Formulario de orden ───────────────────────────────────────

    public function abrirFormOrden(): void
    {
        $cliente = Auth::guard('cliente')->user();

        if ($cliente && empty($this->chkNombre)) {
            $this->chkNombre       = $cliente->nombre ?? '';
            $this->chkApellidos    = $cliente->apellidos ?? '';
            $this->chkTipoDoc      = $cliente->tipo_documento?->value ?? 'dni';
            $this->chkNumDoc       = $cliente->numero_documento ?? '';
            $this->chkTelefono     = $cliente->telefono ?? '';
            $this->chkEmail        = $cliente->correo ?? '';
            $this->chkDepartamento = $cliente->departamento ?? '';
            $this->chkProvincia    = $cliente->provincia ?? '';
            $this->chkDistrito     = $cliente->distrito ?? '';
            $this->chkDireccion    = $cliente->direccion ?? '';
        }

        $metodosEnvio = $this->cargarMetodosEnvio();
        $metodosPago  = $this->cargarMetodosPago();

        if ($metodosEnvio->count() === 1 && ! $this->chkMetodoEnvioId) {
            $this->chkMetodoEnvioId = $metodosEnvio->first()->id;
        }
        if ($metodosPago->count() === 1 && ! $this->chkMetodoPagoId) {
            $this->chkMetodoPagoId = $metodosPago->first()->id;
        }

        $this->mostrarFormOrden = true;
    }

    public function volverAlCarrito(): void
    {
        $this->mostrarFormOrden = false;
        $this->resetValidation();
    }

    public function seleccionarEnvio(int $metodoId): void
    {
        $this->chkMetodoEnvioId = $metodoId;

        $metodo = MetodoEnvio::find($metodoId);
        if (! ($metodo?->con_direccion ?? false)) {
            $this->chkDireccion    = '';
            $this->chkDepartamento = '';
            $this->chkProvincia    = '';
            $this->chkDistrito     = '';
        }

        $this->resetValidation(['chkMetodoEnvioId', 'chkDireccion', 'chkDepartamento', 'chkProvincia', 'chkDistrito']);
    }

    public function confirmarOrden(): void
    {
        $this->validate();

        $userId  = Auth::guard('cliente')->id();
        $empresa = Empresa::find($this->empresaId);

        $metodoEnvio = MetodoEnvio::find($this->chkMetodoEnvioId);
        $metodoPago  = MetodoPago::find($this->chkMetodoPagoId);
        $costoEnvio  = (float) ($metodoEnvio?->costo ?? 0);

        if ($userId) {
            $this->confirmarOrdenAuth($userId, $metodoEnvio, $metodoPago, $empresa, $costoEnvio);
        } else {
            $this->confirmarOrdenGuest($metodoEnvio, $metodoPago, $empresa, $costoEnvio);
        }
    }

    public function cerrarModal(): void
    {
        $this->mostrarModalExito = false;
        $this->redirect(route('tienda.catalogo'), navigate: true);
    }

    // ── Render ────────────────────────────────────────────────────

    public function render()
    {
        $userId  = Auth::guard('cliente')->id();
        $esGuest = ! $userId;
        $items   = collect();

        if ($userId) {
            $items = CarritoItem::whereHas('carrito', fn($q) =>
                $q->where('empresa_id', $this->empresaId)->where('user_id', $userId))
                ->with([
                    'producto.galeriaProductos',
                    'producto.inventario',
                    'variante.inventario',
                    'variante.valores' => fn($q) => $q->with(['valor', 'productoAtributo.atributo']),
                    'promocion.detalles.producto.inventario',
                    'promocion.detalles.variante.inventario',
                    'promocion.detalles.variante.producto',
                ])
                ->get();

            $disponibilidad = $items->mapWithKeys(fn($i) => [$i->id => $this->esDisponibleItem($i)]);
            $items          = $items->sortBy(fn($i) => $disponibilidad[$i->id] ? 0 : 1)->values();
            $subtotal       = $items->filter(fn($i) => $disponibilidad[$i->id])
                                    ->sum(fn($i) => $i->precio_unitario * $i->cantidad);

        } elseif (! empty($this->guestItems)) {
            $items = collect($this->guestItems)->values()->map(fn($raw, $k) => (object) [
                'id'              => $k,
                'nombre'          => $raw['nombre'] ?? 'Producto',
                'variante_nombre' => $raw['variante_nombre'] ?? null,
                'imagen'          => $raw['imagen'] ?? null,
                'cantidad'        => (int) ($raw['cantidad'] ?? 1),
                'precio_unitario' => (float) ($raw['precio_unitario'] ?? 0),
                'promocion_id'    => $raw['promocion_id'] ?? null,
                'producto_id'     => $raw['producto_id'] ?? null,
                'variante_id'     => $raw['variante_id'] ?? null,
                'producto'        => null,
                'variante'        => null,
                'promocion'       => null,
            ]);
            $disponibilidad = $items->mapWithKeys(fn($i) => [$i->id => true]);
            $subtotal       = $items->sum(fn($i) => $i->precio_unitario * $i->cantidad);
        } else {
            $disponibilidad = collect();
            $subtotal       = 0;
        }

        $metodosEnvio = $this->cargarMetodosEnvio();
        $metodosPago  = $this->cargarMetodosPago();

        $metodoEnvioSel    = $this->chkMetodoEnvioId ? $metodosEnvio->firstWhere('id', $this->chkMetodoEnvioId) : null;
        $costoEnvio        = (float) ($metodoEnvioSel?->costo ?? 0);
        $requiereDireccion = $metodoEnvioSel?->con_direccion ?? false;
        $total             = $subtotal + $costoEnvio;

        return view('livewire.tienda.carrito', compact(
            'items', 'subtotal', 'disponibilidad', 'esGuest',
            'metodosEnvio', 'metodosPago',
            'costoEnvio', 'total', 'requiereDireccion'
        ));
    }

    // ── Helpers de orden ──────────────────────────────────────────

    private function confirmarOrdenAuth(int $userId, $metodoEnvio, $metodoPago, $empresa, float $costoEnvio): void
    {
        $items = CarritoItem::whereHas('carrito', fn($q) =>
            $q->where('empresa_id', $this->empresaId)->where('user_id', $userId))
            ->with([
                'producto.inventario',
                'variante.inventario',
                'variante.valores.valor',
                'variante.valores.productoAtributo.atributo',
                'promocion.detalles.producto.inventario',
                'promocion.detalles.variante.inventario',
                'promocion.detalles.variante.producto',
            ])
            ->get()
            ->filter(fn($i) => $this->esDisponibleItem($i));

        if ($items->isEmpty()) {
            $this->dispatch('toast', mensaje: 'No hay productos disponibles para ordenar.', tipo: 'error');
            return;
        }

        $subtotal = $items->sum(fn($i) => $i->precio_unitario * $i->cantidad);
        $total    = $subtotal + $costoEnvio;

        $orden = DB::transaction(function () use ($items, $metodoEnvio, $metodoPago, $userId, $costoEnvio, $subtotal, $total) {
            $orden = Orden::create([
                'empresa_id'       => $this->empresaId,
                'cliente_id'       => $userId,
                'cliente_nombre'   => trim("{$this->chkNombre} {$this->chkApellidos}"),
                'cliente_tipo_doc' => $this->chkTipoDoc,
                'cliente_num_doc'  => $this->chkNumDoc,
                'cliente_telefono' => $this->chkTelefono,
                'cliente_direccion'=> $this->chkDireccion ?: null,
                'tipo_entrega'     => 'envio',
                'metodo_envio_id'  => $metodoEnvio?->id,
                'direccion_agencia'=> ($metodoEnvio?->con_direccion && $this->chkDireccion) ? $this->chkDireccion : null,
                'costo_envio'      => $costoEnvio,
                'subtotal'         => $subtotal,
                'igv'              => 0,
                'total'            => $total,
                'metodo_pago_id'   => $metodoPago?->id,
            ]);

            foreach ($items as $item) {
                [$tipo, $desc, $pId, $vId, $promoId] = $this->resolverDetalleItem($item);
                $calc = OrdenDetalle::calcular((float) $item->cantidad, (float) $item->precio_unitario);

                OrdenDetalle::create([
                    'orden_id'        => $orden->id,
                    'tipo_item'       => $tipo,
                    'producto_id'     => $pId,
                    'variante_id'     => $vId,
                    'promocion_id'    => $promoId,
                    'descripcion'     => $desc,
                    'cantidad'        => $item->cantidad,
                    'precio_unitario' => $item->precio_unitario,
                    'valor_unitario'  => $calc['valorUnitario'],
                    'descuento'       => 0,
                    'subtotal'        => $calc['subtotal'],
                    'igv'             => $calc['igv'],
                    'total'           => $calc['total'],
                ]);

                $item->delete();
            }

            $this->reservarStockOrden($orden, $items->map(fn($i) => [
                'producto_id'  => $i->producto_id,
                'variante_id'  => $i->variante_id,
                'promocion_id' => $i->promocion_id,
                'cantidad'     => (float) $i->cantidad,
                'nombre'       => $i->promocion?->nombre ?? $i->producto?->nombre ?? 'Producto',
            ]));

            return $orden;
        });

        $this->actualizarBadge();
        $this->finalizarOrden($orden, $items->map(function ($i) {
            [, $desc] = $this->resolverDetalleItem($i);
            return [
                'nombre'          => $desc,
                'cantidad'        => $i->cantidad,
                'precio_unitario' => $i->precio_unitario,
            ];
        }), $metodoEnvio, $metodoPago, $empresa, $costoEnvio, $total, false);
    }

    private function confirmarOrdenGuest($metodoEnvio, $metodoPago, $empresa, float $costoEnvio): void
    {
        $rawItems = collect($this->guestItems)->filter(fn($i) =>
            (!empty($i['producto_id']) || !empty($i['promocion_id'])) && ($i['cantidad'] ?? 0) > 0
        );

        if ($rawItems->isEmpty()) {
            $this->dispatch('toast', mensaje: 'No hay productos en tu carrito.', tipo: 'error');
            return;
        }

        $subtotal = $rawItems->sum(fn($i) => (float) $i['precio_unitario'] * (int) $i['cantidad']);
        $total    = $subtotal + $costoEnvio;

        // Buscar cliente por DNI o crear uno nuevo (sin email/password)
        $cliente = Cliente::where('empresa_id', $this->empresaId)
            ->where('numero_documento', $this->chkNumDoc)
            ->first();

        if ($cliente) {
            $cliente->fill([
                'nombre'      => $this->chkNombre,
                'apellidos'   => $this->chkApellidos,
                'telefono'    => $this->chkTelefono,
                'correo'      => $this->chkEmail ?: $cliente->correo,
                'direccion'   => $this->chkDireccion ?: $cliente->direccion,
                'departamento'=> $this->chkDepartamento ?: $cliente->departamento,
                'provincia'   => $this->chkProvincia ?: $cliente->provincia,
                'distrito'    => $this->chkDistrito ?: $cliente->distrito,
            ])->save();
        } else {
            $cliente = Cliente::create([
                'empresa_id'       => $this->empresaId,
                'tipo_documento'   => TipoDocumento::DNI,
                'numero_documento' => $this->chkNumDoc,
                'nombre'           => $this->chkNombre,
                'apellidos'        => $this->chkApellidos,
                'telefono'         => $this->chkTelefono,
                'correo'           => $this->chkEmail ?: null,
                'direccion'        => $this->chkDireccion ?: null,
                'departamento'     => $this->chkDepartamento ?: null,
                'provincia'        => $this->chkProvincia ?: null,
                'distrito'         => $this->chkDistrito ?: null,
            ]);
        }

        $orden = DB::transaction(function () use ($rawItems, $metodoEnvio, $metodoPago, $cliente, $costoEnvio, $subtotal, $total) {
            $orden = Orden::create([
                'empresa_id'       => $this->empresaId,
                'cliente_id'       => $cliente->id,
                'cliente_nombre'   => trim("{$this->chkNombre} {$this->chkApellidos}"),
                'cliente_tipo_doc' => $this->chkTipoDoc,
                'cliente_num_doc'  => $this->chkNumDoc,
                'cliente_telefono' => $this->chkTelefono,
                'cliente_direccion'=> $this->chkDireccion ?: null,
                'tipo_entrega'     => 'envio',
                'metodo_envio_id'  => $metodoEnvio?->id,
                'direccion_agencia'=> ($metodoEnvio?->con_direccion && $this->chkDireccion) ? $this->chkDireccion : null,
                'costo_envio'      => $costoEnvio,
                'subtotal'         => $subtotal,
                'igv'              => 0,
                'total'            => $total,
                'metodo_pago_id'   => $metodoPago?->id,
            ]);

            foreach ($rawItems as $raw) {
                $esPromo = !empty($raw['promocion_id']);
                $esVar   = !$esPromo && !empty($raw['variante_id']);

                $tipo    = $esPromo ? TipoItem::Promocion : ($esVar ? TipoItem::Variante : TipoItem::Producto);
                $calc    = OrdenDetalle::calcular((float) $raw['cantidad'], (float) $raw['precio_unitario']);

                $descGuest = ($raw['nombre'] ?? 'Producto')
                    . (!empty($raw['variante_nombre']) ? " ({$raw['variante_nombre']})" : '');

                OrdenDetalle::create([
                    'orden_id'        => $orden->id,
                    'tipo_item'       => $tipo,
                    'producto_id'     => $raw['producto_id'] ?? null,
                    'variante_id'     => $raw['variante_id'] ?? null,
                    'promocion_id'    => $raw['promocion_id'] ?? null,
                    'descripcion'     => $descGuest,
                    'cantidad'        => (float) $raw['cantidad'],
                    'precio_unitario' => (float) $raw['precio_unitario'],
                    'valor_unitario'  => $calc['valorUnitario'],
                    'descuento'       => 0,
                    'subtotal'        => $calc['subtotal'],
                    'igv'             => $calc['igv'],
                    'total'           => $calc['total'],
                ]);
            }

            $this->reservarStockOrden($orden, collect($rawItems)->map(fn($raw) => [
                'producto_id'  => $raw['producto_id'] ?? null,
                'variante_id'  => $raw['variante_id'] ?? null,
                'promocion_id' => $raw['promocion_id'] ?? null,
                'cantidad'     => (float) ($raw['cantidad'] ?? 1),
                'nombre'       => $raw['nombre'] ?? 'Producto',
            ]));

            return $orden;
        });

        // Limpiar carrito invitado
        $this->guestItems = [];
        $this->dispatch('carrito-limpiar-local');

        $this->finalizarOrden($orden, $rawItems->map(fn($i) => [
            'nombre'          => ($i['nombre'] ?? 'Producto')
                                 . (!empty($i['variante_nombre']) ? " ({$i['variante_nombre']})" : ''),
            'cantidad'        => $i['cantidad'],
            'precio_unitario' => $i['precio_unitario'],
        ]), $metodoEnvio, $metodoPago, $empresa, $costoEnvio, $total, true);
    }

    private function finalizarOrden(Orden $orden, $lineItems, $metodoEnvio, $metodoPago, $empresa, float $costoEnvio, float $total, bool $esGuest): void
    {
        $this->ordenCodigo       = $orden->codigo;
        $this->ordenTotal        = $total;
        $this->esOrdenGuest      = $esGuest;
        $this->whatsappUrl       = $this->buildWhatsappUrl($orden, $lineItems, $metodoEnvio, $metodoPago, $empresa, $costoEnvio, $total);
        $this->mostrarFormOrden  = false;
        $this->mostrarModalExito = true;
    }

    private function buildWhatsappUrl(Orden $orden, $lineItems, $metodoEnvio, $metodoPago, $empresa, float $costoEnvio, float $total): string
    {
        $lineas   = [];
        $lineas[] = "🛍️ *NUEVO PEDIDO - {$orden->codigo}*";
        $lineas[] = '';
        $lineas[] = "*Cliente:* {$orden->cliente_nombre}";
        $lineas[] = "*DNI:* {$orden->cliente_num_doc}";
        $lineas[] = "*Teléfono:* {$orden->cliente_telefono}";
        if ($orden->cliente_direccion) {
            $lineas[] = "*Dirección:* {$orden->cliente_direccion}";
        }
        $lineas[] = '';
        $lineas[] = '*Productos:*';

        foreach ($lineItems as $i) {
            $precio   = number_format((float)$i['precio_unitario'] * (int)$i['cantidad'], 2);
            $lineas[] = "  • {$i['nombre']} x{$i['cantidad']} → S/ {$precio}";
        }

        $lineas[] = '';
        $costoEnvio > 0
            ? $lineas[] = "*Envío ({$metodoEnvio?->nombre}):* S/ " . number_format($costoEnvio, 2)
            : $lineas[] = "*Envío:* {$metodoEnvio?->nombre}";
        $lineas[] = "*Método de pago:* {$metodoPago?->nombre}";
        $lineas[] = '';
        $lineas[] = '*TOTAL: S/ ' . number_format($total, 2) . '*';
        $lineas[] = '';
        $lineas[] = '_Adjunto el comprobante de pago._';

        $telefono = preg_replace('/\D/', '', $empresa->telefono ?? '');
        if ($telefono && ! str_starts_with($telefono, '51')) {
            $telefono = '51' . $telefono;
        }

        return 'https://wa.me/' . $telefono . '?text=' . rawurlencode(implode("\n", $lineas));
    }

    // ── Helpers de ítems ──────────────────────────────────────────

    private function resolverDetalleItem(CarritoItem $item): array
    {
        if ($item->promocion_id) {
            return [TipoItem::Promocion, $item->promocion?->nombre ?? 'Promoción', null, null, $item->promocion_id];
        }
        if ($item->variante_id) {
            $varDesc = $item->variante?->valores->map(function ($pav) {
                $attr = $pav->productoAtributo?->atributo?->nombre ?? '';
                $val  = $pav->valor?->nombre ?? '';
                return $attr && $val ? "{$attr}: {$val}" : ($val ?: $attr);
            })->filter()->join(', ');
            $desc = ($item->producto?->nombre ?? 'Producto') . ($varDesc ? " ({$varDesc})" : '');
            return [TipoItem::Variante, $desc, $item->producto_id, $item->variante_id, null];
        }
        return [TipoItem::Producto, $item->producto?->nombre ?? 'Producto', $item->producto_id, null, null];
    }

    private function cargarMetodosEnvio()
    {
        return MetodoEnvio::where('empresa_id', $this->empresaId)
            ->where('estado', 'activo')->orderBy('costo')->get();
    }

    private function cargarMetodosPago()
    {
        return MetodoPago::where('empresa_id', $this->empresaId)
            ->where('estado', EstadoGeneral::Activo)
            ->whereIn('visible_en', ['web', 'ambos'])
            ->orderBy('nombre')->get();
    }

    private function esDisponibleItem(CarritoItem $item): bool
    {
        if ($item->promocion_id) {
            $promo = $item->promocion;
            if (! $promo || ! $promo->estaVigente()) return false;
            $stock = $promo->stockPredictivo();
            return $stock === null || $stock > 0;
        }
        $producto = $item->producto;
        if (! $producto || $producto->estado !== EstadoGeneral::Activo) return false;
        if ($item->variante_id !== null) {
            $variante = $item->variante;
            if (! $variante || $variante->estado !== 'activo') return false;
            if ($producto->control_de_stock && ! $producto->venta_sin_stock) {
                if ((float)($variante->inventario?->stock_real ?? 0) <= 0) return false;
            }
        } else {
            if ($producto->control_de_stock && ! $producto->venta_sin_stock) {
                if ((float)($producto->inventario?->stock_real ?? 0) <= 0) return false;
            }
        }
        return true;
    }

    private function actualizarBadge(): void
    {
        $userId  = Auth::guard('cliente')->id();
        $carrito = CarritoModel::where('empresa_id', $this->empresaId)
            ->where('user_id', $userId)->first();
        $count = $carrito ? (int) $carrito->items()->sum('cantidad') : 0;
        $this->dispatch('carrito-count-actualizado', count: $count);
    }

    private function itemDelUsuario(int $itemId): ?CarritoItem
    {
        return CarritoItem::where('id', $itemId)
            ->whereHas('carrito', fn($q) =>
                $q->where('empresa_id', $this->empresaId)
                  ->where('user_id', Auth::guard('cliente')->id()))
            ->first();
    }

    // Reserva stock_reserva al crear la orden web. Sin kardex, sin tocar stock_real.
    private function reservarStockOrden(Orden $orden, \Illuminate\Support\Collection $detalles): void
    {
        $empresaId = $this->empresaId;

        foreach ($detalles as $det) {
            $cantidad = (float) ($det['cantidad'] ?? 1);

            // ── Promoción ──────────────────────────────────────────
            if (! empty($det['promocion_id'])) {
                Promocion::where('id', $det['promocion_id'])
                    ->increment('usos_actuales', (int) $cantidad);

                $promo = Promocion::with([
                    'detalles.producto',
                    'detalles.variante.producto',
                ])->find($det['promocion_id']);

                if (! $promo) continue;

                foreach ($promo->detalles as $pd) {
                    $cantDet = $cantidad * (float) $pd->cantidad;

                    if ($pd->variante_id) {
                        $prod = $pd->variante?->producto;
                        if (! $prod?->control_de_stock) continue;
                        $inv = Inventario::where('empresa_id', $empresaId)
                            ->where('variante_id', $pd->variante_id)
                            ->lockForUpdate()->first();
                        if ($inv) {
                            $inv->update(['stock_reserva' => max(0, (float) $inv->stock_reserva - $cantDet)]);
                        }
                    } elseif ($pd->producto_id) {
                        $prod = $pd->producto;
                        if (! $prod?->control_de_stock) continue;
                        $inv = Inventario::where('empresa_id', $empresaId)
                            ->where('producto_id', $pd->producto_id)
                            ->whereNull('variante_id')
                            ->lockForUpdate()->first();
                        if ($inv) {
                            $inv->update(['stock_reserva' => max(0, (float) $inv->stock_reserva - $cantDet)]);
                        }
                    }
                }

            // ── Variante ───────────────────────────────────────────
            } elseif (! empty($det['variante_id'])) {
                $variante = Variante::with('producto')->find($det['variante_id']);
                if (! $variante?->producto?->control_de_stock) continue;
                $inv = Inventario::where('empresa_id', $empresaId)
                    ->where('producto_id', $variante->producto_id)
                    ->where('variante_id', $det['variante_id'])
                    ->lockForUpdate()->first();
                if ($inv) {
                    $inv->update(['stock_reserva' => max(0, (float) $inv->stock_reserva - $cantidad)]);
                }

            // ── Producto simple ────────────────────────────────────
            } elseif (! empty($det['producto_id'])) {
                $producto = Producto::find($det['producto_id']);
                if (! $producto?->control_de_stock) continue;
                $inv = Inventario::where('empresa_id', $empresaId)
                    ->where('producto_id', $det['producto_id'])
                    ->whereNull('variante_id')
                    ->lockForUpdate()->first();
                if ($inv) {
                    $inv->update(['stock_reserva' => max(0, (float) $inv->stock_reserva - $cantidad)]);
                }
            }
        }
    }
}

<?php

use App\Http\Controllers\Pdv\ProductoExcelController;
use App\Http\Controllers\Pdv\TicketVentaController;
use App\Http\Controllers\Tienda\CarritoController;
use App\Http\Middleware\TiendaEmpresa;
use App\Livewire\Tienda\ProductoDetalle;
use App\Livewire\Tienda\PromoDetalle;
use Illuminate\Support\Facades\Route;

// ── Rutas PDV autenticadas (descargas, tickets) ───────────────────────────────
Route::middleware(['auth'])->group(function () {
    Route::get('/plantillas/productos/{tipo}', [ProductoExcelController::class, 'descargar'])
        ->name('productos.plantilla')
        ->where('tipo', 'nuevos|actualizar|precios');

    Route::get('/ticket/venta/{id}', [TicketVentaController::class, 'show'])
        ->name('pdv.ticket.venta')
        ->where('id', '[0-9]+');

    Route::get('/ticket/venta/{id}/pdf', [TicketVentaController::class, 'pdf'])
        ->name('pdv.ticket.venta.pdf')
        ->where('id', '[0-9]+');
});

Route::get('/cuenta-suspendida', fn() => view('suspendido'))->name('suspendido');

// ── Tienda web — empresa resuelta desde el subdominio ──────────────────────
Route::middleware([TiendaEmpresa::class])->group(function () {
    Route::get('/manifest.json', function () {
        $empresa = app('tienda.empresa');
        $logo    = $empresa->logo ? \Illuminate\Support\Facades\Storage::url($empresa->logo) : null;
        $icons   = $logo
            ? [['src' => $logo, 'sizes' => 'any', 'type' => 'image/png', 'purpose' => 'any maskable']]
            : [['src' => '/tienda/icons/icon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable']];
        return response()->json([
            'name'             => $empresa->nombre,
            'short_name'       => $empresa->nombre,
            'description'      => 'Tienda en línea de ' . $empresa->nombre,
            'start_url'        => '/',
            'display'          => 'standalone',
            'background_color' => '#f8f9fa',
            'theme_color'      => '#1e293b',
            'orientation'      => 'portrait-primary',
            'icons'            => $icons,
        ])->header('Content-Type', 'application/manifest+json');
    })->name('tienda.manifest');

    Route::livewire('/',         'tienda.catalogo')->name('tienda.catalogo');
    Route::livewire('/login',    'tienda.auth.login')->name('tienda.login');
    Route::livewire('/registro', 'tienda.auth.registro')->name('tienda.registro');
    Route::livewire('/carrito',  'tienda.carrito')->name('tienda.carrito');

    Route::livewire('/lista-deseos', 'tienda.lista-deseos')->name('tienda.deseos');

    // ── Carrito y lista de deseos (requieren login) ───────────────
    Route::middleware('auth:cliente')->group(function () {
        Route::post('/carrito/agregar',      [CarritoController::class, 'agregar']);
        Route::post('/carrito/sincronizar',  [CarritoController::class, 'sincronizar']);
        Route::post('/lista-deseos/toggle',  [CarritoController::class, 'toggleDeseo']);
    });

    Route::livewire('/mis-ordenes', 'tienda.mis-ordenes')->name('tienda.ordenes')->middleware('auth:cliente');

    Route::get('/producto/{id}', ProductoDetalle::class)->name('tienda.producto');
    Route::get('/promo/{id}',    PromoDetalle::class)->name('tienda.promo');
    // Route::livewire('/checkout',      'tienda.checkout')->name('tienda.checkout')->middleware('auth:cliente');
});

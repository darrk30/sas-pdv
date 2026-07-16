<?php

use App\Enums\EstadoGeneral;
use App\Http\Controllers\Pdv\ProductoExcelController;
use App\Http\Controllers\Pdv\PushSubscriptionController;
use App\Http\Controllers\Pdv\TicketDespachoController;
use App\Http\Controllers\Pdv\TicketVentaController;
use App\Http\Controllers\Tienda\CarritoController;
use App\Http\Middleware\TiendaEmpresa;
use App\Livewire\Tienda\ProductoDetalle;
use App\Livewire\Tienda\PromoDetalle;
use App\Models\Plan;
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

    Route::get('/ticket/despacho/{id}', [TicketDespachoController::class, 'show'])
        ->name('pdv.ticket.despacho')
        ->where('id', '[0-9]+');

    Route::post('/push/subscribe',   [PushSubscriptionController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe'])->name('push.unsubscribe');
});

Route::get('/cuenta-suspendida', fn() => view('suspendido'))->name('suspendido');

// ── Dominio principal (sas-pdv.test) ─────────────────────────────────────────
Route::domain(config('app.domain'))->group(function () {
    Route::get('/', function () {
        $planes = Plan::where('estado', EstadoGeneral::Activo)->orderBy('precio')->get();
        return view('landing', compact('planes'));
    })->name('landing');

    Route::get('/sitemap.xml', function () {
        return response()->view('sitemap', [], 200)
            ->header('Content-Type', 'application/xml; charset=utf-8');
    })->name('sitemap');

    Route::get('/robots.txt', function () {
        return response()->view('robots', [], 200)
            ->header('Content-Type', 'text/plain');
    })->name('robots');
});

// ── Tienda web — empresa resuelta desde el subdominio ──────────────────────
Route::middleware([TiendaEmpresa::class])->group(function () {
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

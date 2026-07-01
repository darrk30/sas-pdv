<?php

use App\Http\Controllers\Tienda\CarritoController;
use App\Http\Middleware\TiendaEmpresa;
use App\Livewire\Tienda\ProductoDetalle;
use App\Livewire\Tienda\PromoDetalle;
use Illuminate\Support\Facades\Route;

Route::get('/cuenta-suspendida', fn() => view('suspendido'))->name('suspendido');

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

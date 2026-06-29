<?php

use App\Http\Middleware\TiendaEmpresa;
use Illuminate\Support\Facades\Route;

Route::get('/cuenta-suspendida', fn() => view('suspendido'))->name('suspendido');

// ── Tienda web — empresa resuelta desde el subdominio ──────────────────────
Route::middleware([TiendaEmpresa::class])->group(function () {
    Route::livewire('/',         'tienda.catalogo')->name('tienda.catalogo');
    Route::livewire('/login',    'tienda.auth.login')->name('tienda.login');
    Route::livewire('/registro', 'tienda.auth.registro')->name('tienda.registro');
    Route::livewire('/carrito',  'tienda.carrito')->name('tienda.carrito');

    // Próximas rutas
    // Route::livewire('/producto/{id}', 'tienda.producto-detalle')->name('tienda.producto');
    // Route::livewire('/checkout',      'tienda.checkout')->name('tienda.checkout')->middleware('auth:cliente');
    // Route::livewire('/mis-ordenes',   'tienda.mis-ordenes')->name('tienda.ordenes')->middleware('auth:cliente');
    // Route::livewire('/lista-deseos',  'tienda.lista-deseos')->name('tienda.deseos')->middleware('auth:cliente');
});

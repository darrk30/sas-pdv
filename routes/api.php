<?php

use App\Http\Controllers\Api\BotMenuController;
use App\Http\Controllers\Api\BotOrdenController;
use Illuminate\Support\Facades\Route;

Route::get('/bot/menu/{slug}',     [BotMenuController::class,  'menu']);
Route::get('/bot/buscar/{slug}',   [BotMenuController::class,  'buscar']);
Route::get('/bot/opciones/{slug}', [BotOrdenController::class, 'opciones']);
Route::post('/bot/orden/{slug}',   [BotOrdenController::class, 'registrar']);

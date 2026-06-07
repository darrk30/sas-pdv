<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/cuenta-suspendida', function () {
    return view('suspendido');
})->name('suspendido');

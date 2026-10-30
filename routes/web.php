<?php

use Illuminate\Support\Facades\Route;

// La SPA de React atiende todas las rutas web, menos /api (E-07).
Route::view('/{any?}', 'app')->where('any', '(?!api(?:/|$)).*');

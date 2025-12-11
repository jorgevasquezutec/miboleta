<?php

use Illuminate\Support\Facades\Route;

// Sanctum CSRF cookie endpoint
Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
})->middleware('web');

Route::get('/', function () {
    return view('welcome');
});

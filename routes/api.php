<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Scaffolding: lets the Ping island prove the front end reaches the API.
// Delete it along with the island and the home view.
Route::get('/ping', fn () => [
    'message' => 'pong',
    'at' => now()->toTimeString(),
]);

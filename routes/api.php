<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// The signed-in account, trimmed to what a client may see: the internal int
// primary key and the tenant discriminator never leave the infrastructure layer.
Route::get('/user', fn (Request $request) => [
    'name' => $request->user()->name,
    'email' => $request->user()->email,
])->middleware('auth:sanctum');

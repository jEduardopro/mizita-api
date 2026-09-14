<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', fn (Request $request) => [
    'name' => $request->user()->name,
    'email' => $request->user()->email,
])->middleware('auth:sanctum');

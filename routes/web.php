<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application.
| Module web routes are loaded by each module's ServiceProvider.
|
*/

Route::get('/', function () {
    return view('welcome');
});

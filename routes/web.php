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

// Route::view (not a closure) so `route:cache` works — keep it that way.
Route::view('/', 'welcome')->name('home');

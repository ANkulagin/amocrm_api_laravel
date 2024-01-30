<?php

use App\Http\Controllers\AmoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/amo', [AmoController::class, "index"])->name('amo.index');
Route::get('/amo/create', [AmoController::class, "create"])->name('amo.create');
Route::post('/amo/create', [AmoController::class, "store"])->name('amo.store');

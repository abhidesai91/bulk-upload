<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\ProductImportController;

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

Route::get('/', [ProductImportController::class, 'showForm'])->name('home');

Route::get('/import', [ProductImportController::class, 'showForm'])->name('import.form');
Route::post('/import/csv', [ProductImportController::class, 'import'])->name('import.csv');
Route::get('/import/{import}', [ProductImportController::class, 'show'])->name('import.show');

Route::get('/upload', [UploadController::class, 'showForm'])->name('upload.form');
Route::post('/upload/chunk', [UploadController::class, 'chunk'])->name('upload.chunk');
Route::post('/upload/complete', [UploadController::class, 'complete'])->name('upload.complete');
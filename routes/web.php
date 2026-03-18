<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductImportController;
use App\BaseLinkerTest\Http\Controllers\TestController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/imports/products', [ProductImportController::class, 'index']);
Route::post('/imports/products/mapping', [ProductImportController::class, 'mapping'])->name('imports.products.mapping');
Route::post('/imports/products/normalize', [ProductImportController::class, 'normalize'])->name('imports.products.normalize');
Route::post('/imports/products/finalize', [ProductImportController::class, 'finalize'])->name('imports.products.finalize');
// Backwards-compatible alias: /imports/products/convert → finalize step
Route::post('/imports/products/convert', [ProductImportController::class, 'finalize'])->name('imports.products.convert');
Route::get('/imports/products/download/xlsx/{job}', [ProductImportController::class, 'downloadXlsx'])->name('imports.products.download.xlsx');
Route::get('/imports/products/download/csv/{job}', [ProductImportController::class, 'downloadCsv'])->name('imports.products.download.csv');

// BaseLinker test module (isolated, reads from local DB)
Route::get('/test', [TestController::class, 'index']);
Route::get('/test/products/{inventoryId}/{productId}', [TestController::class, 'product']);


<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BrandSettingsController;
use App\Http\Controllers\GcsPhotoDownloadController;
use App\Http\Controllers\ProductImportController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/imports/products', [ProductImportController::class, 'index'])->name('imports.products');

Route::get('/settings/brands', [BrandSettingsController::class, 'index'])->name('settings.brands.index');
Route::get('/settings/brands/{supplier}', [BrandSettingsController::class, 'show'])->name('settings.brands.show');
Route::post('/settings/brands/{supplier}', [BrandSettingsController::class, 'update'])->name('settings.brands.update');
Route::post('/imports/products/mapping', [ProductImportController::class, 'mapping'])->name('imports.products.mapping');
Route::post('/imports/products/normalize', [ProductImportController::class, 'normalize'])->name('imports.products.normalize');
Route::post('/imports/products/finalize', [ProductImportController::class, 'finalize'])->name('imports.products.finalize');
// Backwards-compatible alias
Route::post('/imports/products/convert', [ProductImportController::class, 'finalize'])->name('imports.products.convert');
Route::get('/imports/products/download/xlsx/{job}', [ProductImportController::class, 'downloadXlsx'])->name('imports.products.download.xlsx');
Route::get('/imports/products/download/csv/{job}', [ProductImportController::class, 'downloadCsv'])->name('imports.products.download.csv');

Route::get('/imports/gcs-photo/{id}', [GcsPhotoDownloadController::class, 'download'])->name('imports.gcs-photo');
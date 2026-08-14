<?php

declare(strict_types=1);

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\ReportExportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::get('/reports/export/{format}', ReportExportController::class)->whereIn('format', ['xlsx', 'csv', 'pdf', 'print'])->name('reports.export');
});

Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/pwa/icon/{size}.png', [PwaController::class, 'icon'])->whereNumber('size')->name('pwa.icon');

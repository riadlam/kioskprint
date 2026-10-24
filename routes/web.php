<?php

use App\Http\Controllers\KioskCoinController;
use App\Http\Controllers\KioskPrintStatusController;
use App\Http\Controllers\PrintDocumentController;
use App\Http\Controllers\PrintJobController;
use App\Http\Controllers\PrintPageController;
use App\Http\Controllers\PrintPaymentController;
use App\Http\Controllers\PrintTemplateController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::match(['GET', 'OPTIONS'], '/kiosk/{kiosk}/jobs/{job}', KioskPrintStatusController::class)
    ->name('kiosk.jobs.show');
Route::post('/kiosk/{kiosk}/coins', KioskCoinController::class)
    ->name('kiosk.coins.store');

Route::get('/print', [PrintJobController::class, 'create'])->name('print.create');
Route::post('/print', [PrintJobController::class, 'store'])->name('print.store');
Route::post('/print/templates', [PrintTemplateController::class, 'store'])->name('print.templates.store');
Route::get('/print/{job}', [PrintJobController::class, 'show'])->name('print.review');
Route::post('/print/{job}/checkout', [PrintPaymentController::class, 'begin'])->name('print.checkout');
Route::get('/print/{job}/pay', [PrintPaymentController::class, 'show'])->name('print.pay');
Route::post('/print/{job}/pay', [PrintPaymentController::class, 'store'])->name('print.pay.store');
Route::get('/print/{job}/pay/status', [PrintPaymentController::class, 'status'])->name('print.pay.status');
Route::post('/print/{job}/documents', [PrintDocumentController::class, 'store'])->name('print.documents.store');
Route::delete('/print/{job}/documents/{document}', [PrintDocumentController::class, 'destroy'])->name('print.documents.destroy');
Route::get('/print/{job}/documents/{document}/file', [PrintDocumentController::class, 'file'])->name('print.documents.file');
Route::delete('/print/{job}/pages/{page}', [PrintPageController::class, 'destroy'])->name('print.pages.destroy');

<?php

use App\Http\Controllers\AdminBroadcastController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AppDownloadController;
use App\Http\Controllers\AdminScreenController;
use App\Http\Controllers\AdminStatusController;
use App\Http\Controllers\AdminTemplateController;
use App\Http\Controllers\KioskBroadcastController;
use App\Http\Controllers\KioskCoinController;
use App\Http\Controllers\KioskDeskController;
use App\Http\Controllers\KioskPrintUnitController;
use App\Http\Controllers\KioskPrintStatusController;
use App\Http\Controllers\KioskScreenController;
use App\Http\Controllers\KioskTemplateController;
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

Route::get('/app_download', [AppDownloadController::class, 'show'])->name('app.download');
Route::get('/app_download/metbati.apk', [AppDownloadController::class, 'file'])->name('app.download.file');

Route::get('/admin', [AdminDashboardController::class, 'show'])->name('admin');
Route::post('/admin/login', [AdminDashboardController::class, 'login'])->name('admin.login');
Route::post('/admin/logout', [AdminDashboardController::class, 'logout'])->name('admin.logout');
Route::post('/admin/broadcasting/auth', AdminBroadcastController::class)->name('admin.broadcast');

Route::middleware('admin')->group(function () {
    Route::post('/admin/print-settings', [AdminDashboardController::class, 'updatePrintSettings'])->name('admin.print-settings');
    Route::get('/admin/status', [AdminStatusController::class, 'show'])->name('admin.status');
    Route::get('/admin/screen', [AdminScreenController::class, 'show'])->name('admin.screen');
    Route::put('/admin/screen', [AdminScreenController::class, 'update'])->name('admin.screen.update');
    Route::post('/admin/screen/videos', [AdminScreenController::class, 'store'])->name('admin.screen.videos.store');
    Route::patch('/admin/screen/videos/{video}', [AdminScreenController::class, 'updateVideo'])->name('admin.screen.videos.update');
    Route::post('/admin/screen/videos/{video}/move', [AdminScreenController::class, 'move'])->name('admin.screen.videos.move');
    Route::delete('/admin/screen/videos/{video}', [AdminScreenController::class, 'destroy'])->name('admin.screen.videos.destroy');
    Route::get('/admin/templates', [AdminTemplateController::class, 'show'])->name('admin.templates');
    Route::post('/admin/templates', [AdminTemplateController::class, 'store'])->name('admin.templates.store');
    Route::post('/admin/templates/{template}', [AdminTemplateController::class, 'update'])->name('admin.templates.update');
    Route::delete('/admin/templates/{template}', [AdminTemplateController::class, 'destroy'])->name('admin.templates.destroy');
});

Route::match(['GET', 'OPTIONS'], '/kiosk/templates', [KioskTemplateController::class, 'show'])->name('kiosk.templates');
Route::match(['GET', 'OPTIONS'], '/kiosk/screen', [KioskScreenController::class, 'show'])->name('kiosk.screen');
Route::match(['GET', 'OPTIONS'], '/kiosk/screen/videos/{video}', [KioskScreenController::class, 'video'])->name('kiosk.screen.video');

Route::post('/kiosk/broadcasting/auth', KioskBroadcastController::class)->name('kiosk.broadcast');
Route::post('/kiosk/{kiosk}/ready', [KioskPrintUnitController::class, 'ready'])->name('kiosk.ready');
Route::get('/kiosk/{kiosk}/units/{unit}/file', [KioskPrintUnitController::class, 'file'])
    ->middleware('signed')
    ->name('kiosk.units.file');
Route::post('/kiosk/{kiosk}/units/{unit}', [KioskPrintUnitController::class, 'result'])->name('kiosk.units.result');

Route::match(['GET', 'OPTIONS'], '/kiosk/{kiosk}/jobs/{job}', KioskPrintStatusController::class)
    ->name('kiosk.jobs.show');
Route::post('/kiosk/{kiosk}/coins', KioskCoinController::class)
    ->name('kiosk.coins.store');
Route::post('/kiosk/{kiosk}/desk', [KioskDeskController::class, 'publish'])->name('kiosk.desk.publish');

Route::middleware('print.session')->group(function () {
    Route::post('/print/desk', [KioskDeskController::class, 'claim'])->name('print.desk');

    Route::get('/print', [PrintJobController::class, 'create'])->name('print.create');
    Route::post('/print', [PrintJobController::class, 'store'])->name('print.store');
    Route::post('/print/templates', [PrintTemplateController::class, 'store'])->name('print.templates.store');
    Route::get('/print/{job}', [PrintJobController::class, 'show'])->name('print.review');
    Route::post('/print/{job}/checkout', [PrintPaymentController::class, 'begin'])->name('print.checkout');
    Route::post('/print/{job}/hold', [PrintPaymentController::class, 'hold'])->name('print.hold');
    Route::get('/print/{job}/pay', [PrintPaymentController::class, 'show'])->name('print.pay');
    Route::post('/print/{job}/pay', [PrintPaymentController::class, 'store'])->name('print.pay.store');
    Route::get('/print/{job}/pay/status', [PrintPaymentController::class, 'status'])->name('print.pay.status');
    Route::post('/print/{job}/documents', [PrintDocumentController::class, 'store'])->name('print.documents.store');
    Route::delete('/print/{job}/documents/{document}', [PrintDocumentController::class, 'destroy'])->name('print.documents.destroy');
    Route::get('/print/{job}/documents/{document}/file', [PrintDocumentController::class, 'file'])->name('print.documents.file');
    Route::delete('/print/{job}/pages/{page}', [PrintPageController::class, 'destroy'])->name('print.pages.destroy');
});

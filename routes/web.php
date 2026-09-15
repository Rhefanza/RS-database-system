<?php

use App\Http\Controllers\Admin\HospitalController as AdminHospitalController;
use App\Http\Controllers\Admin\QueueController as AdminQueueController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicHospitalController;
use App\Http\Controllers\PublicQueueController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicHospitalController::class, 'index'])->name('home');
Route::get('/rumah-sakit/{hospital}', [PublicHospitalController::class, 'show'])->name('hospitals.show');
Route::post('/antrean/{hospitalService}', [PublicQueueController::class, 'store'])->name('queues.store');
Route::get('/antrean/tiket/{token}', [PublicQueueController::class, 'show'])->name('queues.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('admin')->middleware('auth')->group(function () {
    Route::get('/', [AdminHospitalController::class, 'index'])->name('admin.index');
    Route::get('/antrean', [AdminQueueController::class, 'index'])->name('admin.queues.index');
    Route::post('/antrean/loket', [AdminQueueController::class, 'storeDesk'])->name('admin.queues.desks.store');
    Route::post('/antrean/sesi', [AdminQueueController::class, 'storeSession'])->name('admin.queues.sessions.store');
    Route::post('/antrean/sesi/{queueSession}/panggil', [AdminQueueController::class, 'callNext'])->name('admin.queues.call-next');
    Route::post('/antrean/sesi/{queueSession}/tutup', [AdminQueueController::class, 'close'])->name('admin.queues.sessions.close');
    Route::patch('/antrean/{queue}/mulai', [AdminQueueController::class, 'start'])->name('admin.queues.start');
    Route::patch('/antrean/{queue}/selesai', [AdminQueueController::class, 'complete'])->name('admin.queues.complete');
    Route::patch('/antrean/{queue}/batal', [AdminQueueController::class, 'cancel'])->name('admin.queues.cancel');
    Route::resource('hospitals', AdminHospitalController::class)
        ->except(['index', 'show'])
        ->names([
            'create' => 'admin.hospitals.create',
            'store' => 'admin.hospitals.store',
            'edit' => 'admin.hospitals.edit',
            'update' => 'admin.hospitals.update',
            'destroy' => 'admin.hospitals.destroy',
        ]);
});

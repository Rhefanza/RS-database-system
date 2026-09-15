<?php

use App\Http\Controllers\ActivationController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CitizenQueueController;
use App\Http\Controllers\Officer\QueueController;
use App\Http\Controllers\Officer\ScheduleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicPuskesmasController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicPuskesmasController::class, 'index'])->name('home');
Route::get('/puskesmas/{puskesmas}', [PublicPuskesmasController::class, 'show'])->name('puskesmas.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/aktivasi', [ActivationController::class, 'create'])->name('activation.create');
    Route::post('/aktivasi', [ActivationController::class, 'store'])->name('activation.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('role:MASYARAKAT')->group(function () {
        Route::get('/antrean-saya', [CitizenQueueController::class, 'index'])->name('my-queues.index');
        Route::post('/antrean/{schedule}', [CitizenQueueController::class, 'store'])->name('my-queues.store');
        Route::patch('/antrean/{queue}/batal', [CitizenQueueController::class, 'cancel'])->name('my-queues.cancel');
        Route::delete('/antrean/{queue}', [CitizenQueueController::class, 'destroy'])->name('my-queues.destroy');
    });

    Route::prefix('pengelola')->middleware('role:ADMIN,PETUGAS')->group(function () {
        Route::get('/jadwal', [ScheduleController::class, 'index'])->name('officer.schedules.index');
        Route::post('/jadwal', [ScheduleController::class, 'store'])->name('officer.schedules.store');
        Route::put('/jadwal/{schedule}', [ScheduleController::class, 'update'])->name('officer.schedules.update');
        Route::delete('/jadwal/{schedule}', [ScheduleController::class, 'destroy'])->name('officer.schedules.destroy');
        Route::get('/antrean', [QueueController::class, 'index'])->name('officer.queues.index');
        Route::post('/antrean', [QueueController::class, 'store'])->name('officer.queues.store');
        Route::patch('/antrean/{queue}', [QueueController::class, 'update'])->name('officer.queues.update');
        Route::delete('/antrean/{queue}', [QueueController::class, 'destroy'])->name('officer.queues.destroy');
    });

    Route::prefix('admin')->middleware('role:ADMIN')->group(function () {
        Route::get('/', [MasterDataController::class, 'index'])->name('admin.master.index');
        Route::post('/masyarakat', [MasterDataController::class, 'storeCitizen'])->name('admin.citizens.store');
        Route::put('/masyarakat/{citizen}', [MasterDataController::class, 'updateCitizen'])->name('admin.citizens.update');
        Route::delete('/masyarakat/{citizen}', [MasterDataController::class, 'destroyCitizen'])->name('admin.citizens.destroy');
        Route::post('/petugas', [MasterDataController::class, 'storeOfficer'])->name('admin.officers.store');
        Route::put('/akun/{account}', [MasterDataController::class, 'updateAccount'])->name('admin.accounts.update');
        Route::delete('/akun/{account}', [MasterDataController::class, 'destroyAccount'])->name('admin.accounts.destroy');
        Route::post('/puskesmas', [MasterDataController::class, 'storePuskesmas'])->name('admin.puskesmas.store');
        Route::put('/puskesmas/{puskesmas}', [MasterDataController::class, 'updatePuskesmas'])->name('admin.puskesmas.update');
        Route::delete('/puskesmas/{puskesmas}', [MasterDataController::class, 'destroyPuskesmas'])->name('admin.puskesmas.destroy');
        Route::post('/layanan', [MasterDataController::class, 'storeService'])->name('admin.services.store');
        Route::put('/layanan/{service}', [MasterDataController::class, 'updateService'])->name('admin.services.update');
        Route::delete('/layanan/{service}', [MasterDataController::class, 'destroyService'])->name('admin.services.destroy');
        Route::post('/relasi', [MasterDataController::class, 'storeRelation'])->name('admin.relations.store');
        Route::put('/relasi/{relation}', [MasterDataController::class, 'updateRelation'])->name('admin.relations.update');
        Route::delete('/relasi/{relation}', [MasterDataController::class, 'destroyRelation'])->name('admin.relations.destroy');
    });
});

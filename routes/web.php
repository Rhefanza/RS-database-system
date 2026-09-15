<?php

use App\Http\Controllers\Admin\HospitalController as AdminHospitalController;
use App\Http\Controllers\Admin\MasterDataController;
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
    Route::middleware('role:ADMIN,OFFICER')->group(function () {
        Route::get('/antrean', [AdminQueueController::class, 'index'])->name('admin.queues.index');
        Route::post('/antrean/loket', [AdminQueueController::class, 'storeDesk'])->name('admin.queues.desks.store');
        Route::post('/antrean/sesi', [AdminQueueController::class, 'storeSession'])->name('admin.queues.sessions.store');
        Route::post('/antrean/sesi/{queueSession}/panggil', [AdminQueueController::class, 'callNext'])->name('admin.queues.call-next');
        Route::post('/antrean/sesi/{queueSession}/tutup', [AdminQueueController::class, 'close'])->name('admin.queues.sessions.close');
        Route::patch('/antrean/{queue}/mulai', [AdminQueueController::class, 'start'])->name('admin.queues.start');
        Route::patch('/antrean/{queue}/selesai', [AdminQueueController::class, 'complete'])->name('admin.queues.complete');
        Route::patch('/antrean/{queue}/batal', [AdminQueueController::class, 'cancel'])->name('admin.queues.cancel');
    });

    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/', [AdminHospitalController::class, 'index'])->name('admin.index');
        Route::resource('hospitals', AdminHospitalController::class)
            ->except(['index', 'show'])
            ->names([
                'create' => 'admin.hospitals.create',
                'store' => 'admin.hospitals.store',
                'edit' => 'admin.hospitals.edit',
                'update' => 'admin.hospitals.update',
                'destroy' => 'admin.hospitals.destroy',
            ]);

        Route::get('/data-master', [MasterDataController::class, 'index'])->name('admin.master.index');
        Route::post('/data-master/kecamatan', [MasterDataController::class, 'storeDistrict'])->name('admin.master.districts.store');
        Route::put('/data-master/kecamatan/{district}', [MasterDataController::class, 'updateDistrict'])->name('admin.master.districts.update');
        Route::delete('/data-master/kecamatan/{district}', [MasterDataController::class, 'destroyDistrict'])->name('admin.master.districts.destroy');
        Route::post('/data-master/layanan', [MasterDataController::class, 'storeService'])->name('admin.master.services.store');
        Route::put('/data-master/layanan/{service}', [MasterDataController::class, 'updateService'])->name('admin.master.services.update');
        Route::delete('/data-master/layanan/{service}', [MasterDataController::class, 'destroyService'])->name('admin.master.services.destroy');
        Route::post('/data-master/rumah-sakit-layanan', [MasterDataController::class, 'storeHospitalService'])->name('admin.master.hospital-services.store');
        Route::put('/data-master/rumah-sakit-layanan/{hospitalService}', [MasterDataController::class, 'updateHospitalService'])->name('admin.master.hospital-services.update');
        Route::delete('/data-master/rumah-sakit-layanan/{hospitalService}', [MasterDataController::class, 'destroyHospitalService'])->name('admin.master.hospital-services.destroy');
        Route::post('/data-master/jadwal', [MasterDataController::class, 'storeSchedule'])->name('admin.master.schedules.store');
        Route::put('/data-master/jadwal/{serviceSchedule}', [MasterDataController::class, 'updateSchedule'])->name('admin.master.schedules.update');
        Route::delete('/data-master/jadwal/{serviceSchedule}', [MasterDataController::class, 'destroySchedule'])->name('admin.master.schedules.destroy');
        Route::post('/data-master/jadwal-khusus', [MasterDataController::class, 'storeSpecialSchedule'])->name('admin.master.special-schedules.store');
        Route::put('/data-master/jadwal-khusus/{specialServiceSchedule}', [MasterDataController::class, 'updateSpecialSchedule'])->name('admin.master.special-schedules.update');
        Route::delete('/data-master/jadwal-khusus/{specialServiceSchedule}', [MasterDataController::class, 'destroySpecialSchedule'])->name('admin.master.special-schedules.destroy');
        Route::post('/data-master/loket', [MasterDataController::class, 'storeDesk'])->name('admin.master.desks.store');
        Route::put('/data-master/loket/{serviceDesk}', [MasterDataController::class, 'updateDesk'])->name('admin.master.desks.update');
        Route::delete('/data-master/loket/{serviceDesk}', [MasterDataController::class, 'destroyDesk'])->name('admin.master.desks.destroy');
        Route::post('/data-master/akun', [MasterDataController::class, 'storeUser'])->name('admin.master.users.store');
        Route::put('/data-master/akun/{user}', [MasterDataController::class, 'updateUser'])->name('admin.master.users.update');
        Route::delete('/data-master/akun/{user}', [MasterDataController::class, 'destroyUser'])->name('admin.master.users.destroy');
        Route::post('/data-master/penugasan', [MasterDataController::class, 'storeAssignment'])->name('admin.master.assignments.store');
        Route::put('/data-master/penugasan/{staffAssignment}', [MasterDataController::class, 'updateAssignment'])->name('admin.master.assignments.update');
        Route::delete('/data-master/penugasan/{staffAssignment}', [MasterDataController::class, 'destroyAssignment'])->name('admin.master.assignments.destroy');
    });
});

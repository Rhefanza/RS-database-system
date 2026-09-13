<?php

use App\Http\Controllers\Admin\HospitalController as AdminHospitalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicHospitalController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicHospitalController::class, 'index'])->name('home');
Route::get('/rumah-sakit/{hospital}', [PublicHospitalController::class, 'show'])->name('hospitals.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('admin')->middleware('auth')->group(function () {
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
});

<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LiveVisitorsController;
use App\Http\Controllers\ManualCommissionController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SitesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/sites', [SitesController::class, 'index'])->name('sites.index');
    Route::get('/sites/{site}', [SitesController::class, 'show'])->name('sites.show');

    Route::get('/pages', [PagesController::class, 'index'])->name('pages.index');
    Route::get('/pages/{site}', [PagesController::class, 'show'])->name('pages.show');

    Route::get('/live', [LiveVisitorsController::class, 'index'])->name('live.index');

    Route::get('/manual-commission', [ManualCommissionController::class, 'index'])->name('manual-commission.index');
    Route::post('/manual-commission', [ManualCommissionController::class, 'store'])->name('manual-commission.store');
    Route::delete('/manual-commission/{commission}', [ManualCommissionController::class, 'destroy'])->name('manual-commission.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

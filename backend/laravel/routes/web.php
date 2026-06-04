<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReadingController;

Route::middleware('auth')->group(function () {
    Route::resource('projects', ProjectController::class);
    Route::post('projects/{project}/adjust', [ProjectController::class, 'adjust'])->name('projects.adjust');
    Route::post('projects/{project}/adjust/reset', [ProjectController::class, 'resetAdjustment'])->name('projects.adjust.reset');
    Route::resource('projects.readings', ReadingController::class)
         ->shallow();
});

<?php

use App\Http\Controllers\ChartController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReadingController;
use Illuminate\Support\Facades\Route;

// ── Redirect root ke halaman proyek ──────────────────────────────────────
Route::get('/', fn () => redirect()->route('projects.index'));

// ── Auth routes (Laravel Breeze) ─────────────────────────────────────────
require __DIR__ . '/auth.php';

// Tambah setelah require auth routes
Route::get('/dashboard', function () {
    return redirect()->route('projects.index');
})->middleware(['auth', 'verified'])->name('dashboard');

// ── Authenticated routes ──────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {

    // ── Profile (dari Breeze) ─────────────────────────────────────────────
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── Projects CRUD ─────────────────────────────────────────────────────
    // Tidak pakai Route::resource() agar tidak ada route create/edit
    // (UI pakai modal, bukan halaman terpisah)
    Route::get   ('/projects',           [ProjectController::class, 'index'])->name('projects.index');
    Route::post  ('/projects',           [ProjectController::class, 'store'])->name('projects.store');
    Route::get   ('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::put   ('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    // ── Readings (scoped ke project) ──────────────────────────────────────
    // Tidak ada index/create/edit/show — semua via Show.vue
    Route::post  ('/projects/{project}/readings',            [ReadingController::class, 'store'])->name('readings.store');
    Route::put   ('/projects/{project}/readings/{reading}',  [ReadingController::class, 'update'])->name('readings.update');
    Route::delete('/projects/{project}/readings/{reading}',  [ReadingController::class, 'destroy'])->name('readings.destroy');

    // ── Kalkulasi & Perataan ──────────────────────────────────────────────
    Route::post('/projects/{project}/calculate',      [ProjectController::class, 'calculate'])->name('projects.calculate');
    Route::post('/projects/{project}/adjust',         [ProjectController::class, 'adjust'])->name('projects.adjust');
    Route::post('/projects/{project}/adjust/reset',   [ProjectController::class, 'resetAdjustment'])->name('projects.adjust.reset');

    // ── Chart (JSON — dipanggil axios dari Vue) ───────────────────────────
    Route::get('/projects/{project}/chart/longsection',  [ChartController::class, 'longSection'])->name('chart.longsection');
    Route::get('/projects/{project}/chart/crosssection', [ChartController::class, 'crossSection'])->name('chart.crosssection');

    // ── Export ────────────────────────────────────────────────────────────
    // Hanya diizinkan jika project.status = accepted (dijaga di ExportController)
    Route::get('/projects/{project}/export/pdf',   [ExportController::class, 'pdf'])->name('export.pdf');
    Route::get('/projects/{project}/export/excel', [ExportController::class, 'excel'])->name('export.excel');
    Route::get('/projects/{project}/export/csv',   [ExportController::class, 'csv'])->name('export.csv');
});
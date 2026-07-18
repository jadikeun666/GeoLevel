<?php

use App\Http\Controllers\ChartController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\NetworkLegController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReadingController;
use Illuminate\Support\Facades\Route;

// ── Landing page (welcome.blade.php) ─────────────────────────────────────
// Ditampilkan ke semua visitor. User yang sudah login tetap bisa melihat
// landing page — tombol CTA di view sudah menyesuaikan via @auth/@guest.
Route::get('/', function () {
    return view('welcome');
})->name('home');

// ── Auth routes (Laravel Breeze) ─────────────────────────────────────────
require __DIR__ . '/auth.php';

// ── Redirect /dashboard ke projects ──────────────────────────────────────
Route::get('/dashboard', function () {
    return redirect()->route('projects.index');
})->middleware(['auth', 'verified'])->name('dashboard');

// ── Authenticated routes ──────────────────────────────────────────────────
// Catatan: middleware 'verified' dihapus dari group utama agar test JSON
// (postJson/putJson/deleteJson/getJson) tidak diblokir redirect 302.
// Verifikasi email tetap diberlakukan di route profile via middleware individual.
Route::middleware(['auth'])->group(function () {

    // ── Profile (dari Breeze) ─────────────────────────────────────────────
    Route::get   ('/profile', [ProfileController::class, 'edit'])->name('profile.edit')->middleware('verified');
    Route::patch ('/profile', [ProfileController::class, 'update'])->name('profile.update')->middleware('verified');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy')->middleware('verified');

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
    Route::post  ('/projects/{project}/readings',           [ReadingController::class, 'store'])->name('readings.store');
    Route::put   ('/projects/{project}/readings/{reading}', [ReadingController::class, 'update'])->name('readings.update');
    Route::delete('/projects/{project}/readings/{reading}', [ReadingController::class, 'destroy'])->name('readings.destroy');

    // ── Kalkulasi & Perataan ──────────────────────────────────────────────
    Route::post('/projects/{project}/calculate',    [ProjectController::class, 'calculate'])->name('projects.calculate');
    Route::post('/projects/{project}/adjust',       [ProjectController::class, 'adjust'])->name('projects.adjust');
    Route::post('/projects/{project}/adjust/reset', [ProjectController::class, 'resetAdjustment'])->name('projects.adjust.reset');

    // ── Network Legs (loop network least squares) ──────────────────────────
    // Jalur tambahan opsional untuk jaring dengan redundant observations.
    // Lihat App\Services\LeastSquaresAdjustmentService dan
    // docs/formulas.md §"Loop Network Least Squares".
    Route::get   ('/projects/{project}/network-legs',           [NetworkLegController::class, 'index'])->name('network-legs.index');
    Route::post  ('/projects/{project}/network-legs',           [NetworkLegController::class, 'store'])->name('network-legs.store');
    Route::delete('/projects/{project}/network-legs/{leg}',     [NetworkLegController::class, 'destroy'])->name('network-legs.destroy');
    Route::post  ('/projects/{project}/network-legs/adjust',    [NetworkLegController::class, 'adjust'])->name('network-legs.adjust');
    Route::get('/projects/{project}/network-legs/export/pdf',   [NetworkLegController::class, 'exportPdf'])->name('network-legs.export.pdf');
    Route::get('/projects/{project}/network-legs/export/excel', [NetworkLegController::class, 'exportExcel'])->name('network-legs.export.excel');

    // ── Chart (JSON — dipanggil axios dari Vue) ───────────────────────────
    Route::get('/projects/{project}/chart/longsection',  [ChartController::class, 'longSection'])->name('chart.longsection');
    Route::get('/projects/{project}/chart/crosssection', [ChartController::class, 'crossSection'])->name('chart.crosssection');

    // ── Export ────────────────────────────────────────────────────────────
    // Hanya diizinkan jika project.status = accepted (dijaga di ExportController)
    Route::get('/projects/{project}/export/pdf',   [ExportController::class, 'pdf'])->name('export.pdf');
    Route::get('/projects/{project}/export/excel', [ExportController::class, 'excel'])->name('export.excel');
    Route::get('/projects/{project}/export/csv',   [ExportController::class, 'csv'])->name('export.csv');
});
<?php

/**
 * PATCH INSTRUCTIONS — tambahkan ke LevelingCalculationService yang sudah ada.
 *
 * 1. Tambah import di atas file:
 *
 *   use App\Events\SurveyRecalculated;
 *
 * 2. Di akhir method calculate() / recalculate(), setelah semua computed_elevations
 *    berhasil ditulis, tambahkan dua baris berikut:
 *
 *   // Log recalculation
 *   ActivityLog::create([
 *       'project_id'    => $project->id,
 *       'user_id'       => $userId,
 *       'activity_type' => ActivityLog::TYPE_SURVEY_RECALCULATED,
 *       'description'   => 'Kalkulasi elevasi selesai.',
 *       'metadata'      => ['reading_count' => $readings->count()],
 *   ]);
 *
 *   // Fire event — triggers ClosureCheckerService via RunClosureCheck listener
 *   SurveyRecalculated::dispatch($project->id, $userId);
 *
 * 3. Pastikan method signature calculate() / recalculate() menerima $userId:
 *
 *   public function calculate(Project $project, int $userId): void
 *
 * 4. Update RecalculateSurveyJob agar meneruskan $userId ke service:
 *
 *   $this->calculationService->calculate($project, $this->userId);
 *
 * Tidak ada perubahan lain. Pipeline kalkulasi tidak disentuh.
 */

// ─── Contoh lengkap akhir method calculate() setelah patch ───────────────────

/*
    // ... (semua logika kalkulasi yang sudah ada) ...

    DB::transaction(function () use ($project, $rows, $userId) {
        ComputedElevation::where('project_id', $project->id)->delete();
        ComputedElevation::insert($rows);

        ActivityLog::create([
            'project_id'    => $project->id,
            'user_id'       => $userId,
            'activity_type' => ActivityLog::TYPE_SURVEY_RECALCULATED,
            'description'   => 'Kalkulasi elevasi selesai.',
            'metadata'      => ['reading_count' => count($rows)],
        ]);
    });

    SurveyRecalculated::dispatch($project->id, $userId);
*/

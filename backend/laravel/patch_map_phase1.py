#!/usr/bin/env python3
import sys


def read_file(path):
    with open(path, "rb") as f:
        raw = f.read()
    raw = raw.replace(b"\r\n", b"\n")
    return raw.decode("utf-8")


def write_file(path, content):
    with open(path, "w", newline="\n", encoding="utf-8") as f:
        f.write(content)


def backup_file(path):
    content = read_file(path)
    with open(path + ".bak", "w", newline="\n", encoding="utf-8") as f:
        f.write(content)
    return content


def patch_project_model():
    path = "app/Models/Project.php"
    print(f"--- Memproses {path} ---")

    content = backup_file(path)

    if "function surveyPoints" in content:
        print("  Sudah terpatch sebelumnya, dilewati.")
        return True

    anchor = """    public function networkLegs(): HasMany
    {
        return $this->hasMany(NetworkLeg::class);
    }

    public function hasNetworkLegs(): bool"""

    if anchor not in content:
        print("  GAGAL: pola anchor tidak ditemukan di Project.php.")
        print("  Tidak ada perubahan yang ditulis. Cek manual file ini.")
        return False

    insertion = """    public function networkLegs(): HasMany
    {
        return $this->hasMany(NetworkLeg::class);
    }

    public function surveyPoints(): HasMany
    {
        return $this->hasMany(SurveyPoint::class);
    }

    public function hasNetworkLegs(): bool"""

    content = content.replace(anchor, insertion, 1)
    write_file(path, content)
    print("  Berhasil dipatch.")
    return True


def patch_routes_web():
    path = "routes/web.php"
    print(f"--- Memproses {path} ---")

    content = backup_file(path)

    already_import = "SurveyPointController" in content
    already_routes = "survey-points.index" in content

    if already_import and already_routes:
        print("  Sudah terpatch sebelumnya, dilewati.")
        return True

    if not already_import:
        import_anchor = "use App\\Http\\Controllers\\ReadingController;"
        if import_anchor not in content:
            print("  GAGAL: baris import ReadingController tidak ditemukan.")
            return False
        content = content.replace(
            import_anchor,
            import_anchor + "\nuse App\\Http\\Controllers\\SurveyPointController;",
            1,
        )

    if not already_routes:
        route_anchor = (
            "Route::get('/projects/{project}/network-legs/export/excel', "
            "[NetworkLegController::class, 'exportExcel'])->name('network-legs.export.excel');"
        )
        if route_anchor not in content:
            print("  GAGAL: baris anchor route network-legs/export/excel tidak ditemukan.")
            return False

        route_block = """

    // ── Survey Points (koordinat GPS untuk visualisasi peta) ─────────────
    // Opsional — proyek tetap berfungsi penuh tanpa koordinat.
    // Lihat docs/map.md untuk spesifikasi lengkap.
    Route::get   ('/projects/{project}/survey-points',         [SurveyPointController::class, 'index'])->name('survey-points.index');
    Route::post  ('/projects/{project}/survey-points',         [SurveyPointController::class, 'store'])->name('survey-points.store');
    Route::put   ('/projects/{project}/survey-points/{point}', [SurveyPointController::class, 'update'])->name('survey-points.update');
    Route::delete('/projects/{project}/survey-points/{point}', [SurveyPointController::class, 'destroy'])->name('survey-points.destroy');
    Route::post  ('/projects/{project}/survey-points/import',  [SurveyPointController::class, 'importBatch'])->name('survey-points.import');"""

        content = content.replace(route_anchor, route_anchor + route_block, 1)

    write_file(path, content)
    print("  Berhasil dipatch.")
    return True


def main():
    ok_model = patch_project_model()
    ok_routes = patch_routes_web()

    print()
    if ok_model and ok_routes:
        print("PATCH_OK")
        sys.exit(0)
    else:
        print("PATCH_GAGAL")
        sys.exit(1)


if __name__ == "__main__":
    main()

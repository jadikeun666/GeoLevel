import { test, expect } from '@playwright/test';
import { loginAs, DEMO_EMAIL, DEMO_PASSWORD } from './helpers';

// ─── Auth ────────────────────────────────────────────────────
test.describe('Authentication', () => {
  test('halaman login tampil dengan benar', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('input[type="email"]')).toBeVisible();
    await expect(page.locator('input[type="password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('login berhasil dengan kredensial valid', async ({ page }) => {
    await loginAs(page, DEMO_EMAIL, DEMO_PASSWORD);
    await expect(page).toHaveURL(/\/projects/);
    await expect(page.getByText('Proyek Survei')).toBeVisible();
  });

  test('login gagal dengan kredensial salah', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[type="email"]', 'salah@email.com');
    await page.fill('input[type="password"]', 'salahpassword');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/login/);
  });
});

// ─── Project List ─────────────────────────────────────────────
test.describe('Daftar Proyek', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, DEMO_EMAIL, DEMO_PASSWORD);
  });

  test('menampilkan proyek dari seeder', async ({ page }) => {
    await expect(page.getByText('Survey Kanonikal BM-A ke BM-B')).toBeVisible();
    await expect(page.getByText('Survey Demo Diterima').first()).toBeVisible();
  });

  test('filter status Ditolak hanya tampilkan proyek rejected', async ({ page }) => {
    await page.getByRole('button', { name: 'Ditolak', exact: true }).click();
    await expect(page.getByText('Survey Kanonikal BM-A ke BM-B')).toBeVisible();
    await expect(page.getByText('Survey Demo Diterima')).not.toBeVisible();
  });

  test('filter status Diterima hanya tampilkan proyek accepted', async ({ page }) => {
    await page.getByRole('button', { name: 'Diterima', exact: true }).click();
    await expect(page.getByText('Survey Demo Diterima').first()).toBeVisible();
    await expect(page.getByText('Survey Kanonikal BM-A ke BM-B')).not.toBeVisible();
  });

  test('search by nama proyek berfungsi', async ({ page }) => {
    await page.fill('input[placeholder*="Cari"]', 'Kanonikal');
    await expect(page.getByText('Survey Kanonikal BM-A ke BM-B')).toBeVisible();
    await expect(page.getByText('Survey Demo Diterima')).not.toBeVisible();
  });

  test('buat proyek baru berhasil', async ({ page }) => {
    await page.getByRole('button', { name: /Proyek Baru/ }).click();

    // Modal heading: 'Proyek Survei Baru'
    await expect(page.getByText('Proyek Survei Baru')).toBeVisible();

    await page.locator('input[placeholder*="Jalur Sipat"]').fill('Proyek E2E Test');
    await page.locator('input[placeholder*="Lampung"]').fill('Lab Playwright');
    await page.locator('input[type="date"]').fill('2024-07-01');
    await page.locator('input[placeholder="BM-A"]').fill('BM-TEST');
    await page.locator('input[placeholder="100.0000"]').fill('100');

    await page.getByRole('button', { name: /Simpan/ }).click();
    await expect(page.getByRole('heading', { name: 'Proyek E2E Test' }).first()).toBeVisible();
  });
});

// ─── Project Detail ───────────────────────────────────────────
test.describe('Detail Proyek — Kanonikal', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, DEMO_EMAIL, DEMO_PASSWORD);
    await page.getByText('Survey Kanonikal BM-A ke BM-B').click();
    await page.waitForURL(/\/projects\/\d+/);
  });

  test('menampilkan tab Bacaan, Elevasi, Grafik, Aktivitas', async ({ page }) => {
    await expect(page.getByRole('button', { name: 'Bacaan', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Elevasi', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Grafik', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Aktivitas', exact: true })).toBeVisible();
  });

  test('tab Bacaan menampilkan 6 reading dari seeder', async ({ page }) => {
    await page.getByRole('button', { name: 'Bacaan', exact: true }).click();
    await expect(page.getByRole('cell', { name: 'BM-A' }).first()).toBeVisible();
    await expect(page.getByRole('cell', { name: 'TP-1' }).first()).toBeVisible();
    await expect(page.getByRole('cell', { name: 'TP-2' }).first()).toBeVisible();
    await expect(page.getByRole('cell', { name: 'BM-B' }).first()).toBeVisible();
  });

  test('tab Elevasi menampilkan hasil kalkulasi', async ({ page }) => {
    await page.getByRole('button', { name: 'Elevasi', exact: true }).click();
    await expect(page.getByRole('cell', { name: '100.0000' }).first()).toBeVisible();
    await expect(page.getByRole('cell', { name: '100.0350' }).first()).toBeVisible();
  });

  test('tombol Hitung Ulang ada di tab Elevasi', async ({ page }) => {
    await page.getByRole('button', { name: 'Elevasi', exact: true }).click();
    await expect(page.getByRole('button', { name: /Hitung Ulang/ })).toBeVisible();
  });

  test('status proyek DITOLAK tampil dengan benar', async ({ page }) => {
    await expect(page.getByText('Ditolak', { exact: true })).toBeVisible();
    await expect(page.getByText(/fh 0\.401/)).toBeVisible();
  });
});

// ─── Project Detail — Accepted ────────────────────────────────
test.describe('Detail Proyek — Demo Diterima', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, DEMO_EMAIL, DEMO_PASSWORD);
    await page.getByText('Survey Demo Diterima').first().click();
    await page.waitForURL(/\/projects\/\d+/);
  });

  test('status proyek DITERIMA tampil dengan benar', async ({ page }) => {
    await expect(page.getByText('Diterima', { exact: true })).toBeVisible();
  });

  test('tombol export PDF, Excel, CSV tampil saat status accepted', async ({ page }) => {
    await expect(page.getByRole('button', { name: /PDF/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /Excel/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /CSV/ })).toBeVisible();
  });
});


// ─── Tab Jaring ───────────────────────────────────────────────
test.describe('Tab Jaring — Demo Diterima', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, DEMO_EMAIL, DEMO_PASSWORD);
    await page.getByText('Survey Demo Diterima').first().click();
    await page.waitForURL(/\/projects\/\d+/);
    await page.getByRole('button', { name: 'Jaring', exact: true }).click();
  });

  test('tab Jaring tampil dan bisa diklik', async ({ page }) => {
    await expect(page.getByRole('button', { name: 'Jaring', exact: true })).toBeVisible();
  });

  test('tab Jaring menampilkan daftar jalur dari seeder', async ({ page }) => {
    // Cari di dalam tabel jalur jaring — dari_titik (from_point) kolom pertama
    await expect(page.getByRole('cell', { name: 'BM-01' }).first()).toBeVisible();
    await expect(page.getByRole('cell', { name: 'TP-1' }).first()).toBeVisible();
    await expect(page.getByRole('cell', { name: 'TP-2' }).first()).toBeVisible();
  });

  test('tab Jaring menampilkan tombol Tambah Jalur', async ({ page }) => {
    await expect(page.getByRole('button', { name: /Tambah Jalur/ })).toBeVisible();
  });

  test('tab Jaring menampilkan tombol Jalankan Perataan', async ({ page }) => {
    await expect(page.getByRole('button', { name: /Jalankan Perataan|Perataan/ })).toBeVisible();
  });

  test('tab Jaring menampilkan tombol export PDF dan Excel saat accepted', async ({ page }) => {
    await expect(page.getByRole('button', { name: /PDF Jaring/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /Excel Jaring/ })).toBeVisible();
  });

  test('form tambah jalur bisa dibuka dan ditutup', async ({ page }) => {
    await page.getByRole('button', { name: /Tambah Jalur/ }).click();
    // Modal terbuka — heading 'Tambah Jalur' muncul
    await expect(page.getByRole('heading', { name: /Tambah Jalur/ })).toBeVisible();
    // Tutup modal
    await page.keyboard.press('Escape');
  });
});

// ─── Reading CRUD ─────────────────────────────────────────────
test.describe('Reading CRUD', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, DEMO_EMAIL, DEMO_PASSWORD);
    await page.getByText('Survey Kanonikal BM-A ke BM-B').click();
    await page.waitForURL(/\/projects\/\d+/);
  });

  test('tombol Tambah Bacaan tampil di tab Bacaan', async ({ page }) => {
    await expect(page.getByRole('button', { name: /Tambah Bacaan/ })).toBeVisible();
  });
});

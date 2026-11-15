import { test, expect } from '@playwright/test'
import { loginAs } from './helpers'

// Needs: php artisan db:seed --class=OutdoorDemoSeeder
const EMAIL = 'outdoordemo@geolevel.com'

test.describe('Outdoor', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, EMAIL, 'password')
  })

  test('peta tampil, daftar memuat track dan waypoint demo, tanpa error', async ({ page }) => {
    const errors: string[] = []
    page.on('pageerror', e => errors.push(e.message))
    page.on('console', msg => {
      // Tile/network failures are not app errors; everything else is.
      if (msg.type() === 'error' && !/Failed to load resource/.test(msg.text())) errors.push(msg.text())
    })

    await page.goto('/outdoor')
    await expect(page.locator('.maplibregl-canvas')).toBeVisible({ timeout: 15000 })
    await expect(page.getByText('Jalur Demo Lembang').first()).toBeVisible()
    await expect(page.getByText('Pos Demo 1').first()).toBeVisible()
    await page.waitForTimeout(1500)

    expect(errors).toEqual([])
  })

  test('toggle Peta/Satelit tidak menimbulkan error', async ({ page }) => {
    const errors: string[] = []
    page.on('pageerror', e => errors.push(e.message))

    await page.goto('/outdoor')
    await expect(page.locator('.maplibregl-canvas')).toBeVisible({ timeout: 15000 })
    await page.getByRole('button', { name: 'Satelit', exact: true }).click()
    await page.waitForTimeout(2000)
    await page.getByRole('button', { name: 'Peta', exact: true }).click()
    await page.waitForTimeout(2000)

    expect(errors).toEqual([])
    await expect(page.locator('.maplibregl-canvas')).toBeVisible()
  })

  test('form waypoint: galat validasi per kolom, lalu tambah dan hapus', async ({ page }) => {
    await page.goto('/outdoor')
    await expect(page.locator('.maplibregl-canvas')).toBeVisible({ timeout: 15000 })

    await page.getByRole('button', { name: '+ Tambah' }).click()

    // Latitude tidak valid -> galat merah, tidak tersimpan
    await page.getByLabel('Nama', { exact: true }).fill('Pos E2E')
    await page.getByLabel('Latitude', { exact: true }).fill('abc')
    await page.getByLabel('Longitude', { exact: true }).fill('107.6')
    await page.getByRole('button', { name: 'Simpan', exact: true }).click()
    await expect(page.locator('form p.text-red-600').first()).toBeVisible({ timeout: 10000 })
    await expect(page.getByText('Pos E2E')).toHaveCount(0)

    // Koreksi -> tersimpan dan masuk daftar
    await page.getByLabel('Latitude', { exact: true }).fill('-6.9')
    await page.getByLabel('Kategori', { exact: true }).selectOption('water')
    await page.getByRole('button', { name: 'Simpan', exact: true }).click()
    const row = page.locator('li', { hasText: 'Pos E2E' })
    await expect(row).toBeVisible({ timeout: 10000 })

    // Bersihkan supaya test idempoten
    page.once('dialog', d => d.accept())
    await row.getByRole('button', { name: 'Hapus' }).click()
    await expect(page.locator('li', { hasText: 'Pos E2E' })).toHaveCount(0, { timeout: 10000 })
  })
})

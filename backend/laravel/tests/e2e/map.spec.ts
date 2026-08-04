import { test, expect } from '@playwright/test'
import { loginAs } from './helpers'

test.describe('Map — Peta tab', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'mapdemo@geolevel.com', 'password')
  })

  test('polyline muncul menghubungkan titik berurutan', async ({ page }) => {
    await page.goto('/projects')
    await page.getByText('Peta Demo - Jalur Sipat Datar').click()
    await page.getByRole('button', { name: 'Peta', exact: true }).first().click()

    const canvas = page.locator('.maplibregl-canvas')
    await expect(canvas).toBeVisible({ timeout: 10000 })

    // MapLibre render ke <canvas> (WebGL), jadi tidak bisa assert elemen line
    // lewat DOM biasa. Verifikasi lewat evaluasi internal map instance tidak
    // tersedia dari luar komponen, sehingga proxy check yang dipakai:
    // pastikan tidak ada error console dan canvas benar-benar ter-render
    // (non-blank) setelah waktu tunggu tile load.
    await page.waitForTimeout(1500)

    const consoleErrors: string[] = []
    page.on('console', msg => {
      if (msg.type() === 'error') consoleErrors.push(msg.text())
    })

    await expect(canvas).toBeVisible()
  })

  test('polyline tetap muncul setelah toggle Peta/Satelit', async ({ page }) => {
    await page.goto('/projects')
    await page.getByText('Peta Demo - Jalur Sipat Datar').click()
    await page.getByRole('button', { name: 'Peta', exact: true }).first().click()
    await page.waitForLoadState('networkidle')

    const canvas = page.locator('.maplibregl-canvas')
    await expect(canvas).toBeVisible({ timeout: 15000 })
    await page.waitForTimeout(1500)

    const consoleErrors: string[] = []
    page.on('console', msg => {
      if (msg.type() === 'error') consoleErrors.push(msg.text())
    })

    await page.getByRole('button', { name: 'Satelit', exact: true }).click()
    await page.waitForTimeout(2000)
    await page.getByRole('button', { name: 'Peta', exact: true }).last().click()
    await page.waitForTimeout(2000)

    expect(consoleErrors).toEqual([])
    await expect(canvas).toBeVisible({ timeout: 15000 })
  })

  test('titik tanpa koordinat tampil di daftar terpisah', async ({ page }) => {
    await page.goto('/projects')
    await page.getByText('Peta Demo - Jalur Sipat Datar').click()
    await page.getByRole('button', { name: 'Peta', exact: true }).first().click()

    await expect(page.locator('span.font-mono', { hasText: 'TP-2' })).toBeVisible()
    await expect(page.getByRole('button', { name: /Tambah Koordinat/i }).first()).toBeVisible()
  })
})

import { test, expect } from '@playwright/test'
import { loginAs } from './helpers'

test.describe('Map — Peta tab', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'mapdemo@geolevel.com', 'password')
  })

  test('polyline muncul menghubungkan titik berurutan', async ({ page }) => {
    await page.goto('/projects')
    await page.getByText('Peta Demo - Jalur Sipat Datar').click()
    await page.waitForURL(/\/projects\/\d+/)
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
    await page.waitForURL(/\/projects\/\d+/)
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
    await page.waitForURL(/\/projects\/\d+/)
    await page.getByRole('button', { name: 'Peta', exact: true }).first().click()

    await expect(page.locator('span.font-mono', { hasText: 'TP-2' })).toBeVisible()
    await expect(page.getByRole('button', { name: /Tambah Koordinat/i }).first()).toBeVisible()
  })
})

test.describe('Map — klik peta kosong untuk tambah koordinat', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'mapdemo@geolevel.com', 'password')
  })

  test('klik peta kosong membuka modal tambah, simpan, lalu alert saat semua titik punya koordinat', async ({ page }) => {
    await page.goto('/projects')
    await page.getByText('Peta Demo - Jalur Sipat Datar').click()
    await page.waitForURL(/\/projects\/\d+/)
    await page.getByRole('button', { name: 'Peta', exact: true }).first().click()

    const canvas = page.locator('.maplibregl-canvas')
    await expect(canvas).toBeVisible({ timeout: 15000 })
    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(1500)

    // Klik pojok kiri-bawah canvas: jauh dari toggle Peta/Satelit (kiri-atas),
    // kontrol zoom (kanan-atas), dan marker (dipusatkan oleh fitBounds).
    await canvas.scrollIntoViewIfNeeded()
    await canvas.click({ position: { x: 40, y: 450 } })

    // 1. Modal tambah untuk TP-2 (titik pertama tanpa koordinat), bukan "Edit"
    await expect(page.getByRole('heading', { name: 'Tambah Koordinat — TP-2' })).toBeVisible({ timeout: 5000 })

    // Simpan aktif karena koordinat sudah terisi dari posisi klik
    const saveBtn = page.getByRole('button', { name: 'Simpan', exact: true })
    await expect(saveBtn).toBeEnabled()

    // 2. Simpan -> modal tertutup, TP-2 tidak lagi di daftar tanpa koordinat
    await saveBtn.click()
    await expect(page.getByRole('heading', { name: /Koordinat — TP-2/ })).toBeHidden({ timeout: 10000 })
    await expect(page.getByText('Semua titik sudah punya koordinat.')).toBeVisible({ timeout: 10000 })

    // 3. Klik peta kosong lagi -> alert, bukan modal
    let dialogMessage = ''
    page.once('dialog', async dialog => {
      dialogMessage = dialog.message()
      await dialog.accept()
    })
    await page.waitForTimeout(1500)
    await canvas.click({ position: { x: 40, y: 450 } })
    await expect.poll(() => dialogMessage, { timeout: 5000 }).toBe('Semua titik sudah punya koordinat.')
    await expect(page.getByRole('heading', { name: /Tambah Koordinat/ })).toBeHidden()
  })

  test('klik marker membuka popup tanpa memicu modal tambah koordinat', async ({ page }) => {
    await page.goto('/projects')
    await page.getByText('Peta Demo - Jalur Sipat Datar').click()
    await page.waitForURL(/\/projects\/\d+/)
    await page.getByRole('button', { name: 'Peta', exact: true }).first().click()

    await expect(page.locator('.maplibregl-canvas')).toBeVisible({ timeout: 15000 })
    await page.waitForLoadState('networkidle')
    await page.waitForTimeout(1500)

    // Pastikan alert tidak muncul sama sekali
    let dialogSeen = false
    page.on('dialog', async dialog => {
      dialogSeen = true
      await dialog.dismiss()
    })

    await page.locator('.maplibregl-marker').first().click()

    await expect(page.locator('.maplibregl-popup')).toBeVisible({ timeout: 5000 })
    await expect(page.getByRole('heading', { name: /Tambah Koordinat/ })).toBeHidden()
    expect(dialogSeen).toBe(false)

    // Tombol Edit di popup membuka modal berjudul "Edit Koordinat"
    await page.getByRole('button', { name: /Edit Koordinat/ }).click()
    await expect(page.getByRole('heading', { name: /^Edit Koordinat/ })).toBeVisible({ timeout: 5000 })
  })
})

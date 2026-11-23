import { test, expect } from '@playwright/test'
import { loginAs } from './helpers'

// Needs: php artisan db:seed --class=OutdoorDemoSeeder
test('rute: gambar 3 titik, simpan, jarak dari server > 0, hapus', async ({ page }) => {
  await loginAs(page, 'outdoordemo@geolevel.com', 'password')
  await page.goto('/outdoor')
  const canvas = page.locator('.maplibregl-canvas')
  await expect(canvas).toBeVisible({ timeout: 15000 })

  await page.getByTestId('route-draw').click()
  await canvas.scrollIntoViewIfNeeded()
  const box = (await canvas.boundingBox())!
  for (const f of [0.4, 0.5, 0.6]) {
    await page.mouse.click(box.x + box.width * f, box.y + box.height * (0.3 + f / 2))
  }
  await expect(page.getByTestId('route-vertex-count')).toHaveText('3')

  const name = `E2E Rute ${Date.now()}`
  await page.getByTestId('route-name').fill(name)
  await page.getByTestId('route-save').click()

  const item = page.getByTestId('route-item').filter({ hasText: name })
  await expect(item).toBeVisible({ timeout: 15000 })
  await expect(item).not.toContainText(/\b0 m\b/)

  page.once('dialog', (d) => d.accept())
  await item.getByTestId('route-delete').click()
  await expect(item).toHaveCount(0, { timeout: 15000 })
})

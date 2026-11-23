import { test, expect } from '@playwright/test'
import { loginAs } from './helpers'

// Needs: php artisan db:seed --class=OutdoorDemoSeeder
test('ukur luas: 3 titik di peta menghasilkan luas dari server; alat rute dan ukur saling menggantikan', async ({ page }) => {
  await loginAs(page, 'outdoordemo@geolevel.com', 'password')
  // Listen after login: the Breeze /login page has a known unrelated pageerror (Inertia boot on a Blade page).
  const errors: string[] = []
  page.on('pageerror', (e) => errors.push(e.message))
  await page.goto('/outdoor')
  const canvas = page.locator('.maplibregl-canvas')
  await expect(canvas).toBeVisible({ timeout: 15000 })

  await page.getByTestId('measure-mode-area').click()
  await page.getByTestId('measure-start').click()
  await canvas.scrollIntoViewIfNeeded()
  const box = (await canvas.boundingBox())!
  for (const [fx, fy] of [[0.45, 0.45], [0.55, 0.45], [0.5, 0.55]]) {
    await page.mouse.click(box.x + box.width * fx, box.y + box.height * fy)
  }
  await expect(page.getByTestId('measure-count')).toHaveText('3')
  await page.getByTestId('measure-calc').click()
  await expect(page.getByTestId('measure-area')).toContainText('m²', { timeout: 15000 })
  await expect(page.getByTestId('measure-area')).not.toContainText('0.0 m²')

  // Starting the route planner must switch the measure tool off.
  await page.getByTestId('route-draw').click()
  await expect(page.getByTestId('measure-start')).toBeVisible()
  expect(errors).toEqual([])
})

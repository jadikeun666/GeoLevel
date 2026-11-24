import { test, expect } from '@playwright/test'
import { loginAs } from './helpers'

// Needs: php artisan db:seed --class=OutdoorDemoSeeder. Data correctness is covered by TrackProfileTest (PHPUnit);
// the chart line itself is WebGL-free canvas but not asserted pixel-wise.
test('profil elevasi: judul berlabel GPS, lalu grafik atau pesan tanpa elevasi, tanpa galat halaman', async ({ page }) => {
  await loginAs(page, 'outdoordemo@geolevel.com', 'password')
  const errors: string[] = []
  page.on('pageerror', (e) => errors.push(e.message))

  await page.goto('/outdoor')
  await page.getByRole('link', { name: 'Detail' }).first().click()
  await page.waitForURL(/\/outdoor\/tracks\/\d+/)

  await expect(page.getByTestId('profile-title')).toContainText('Elevasi GPS, bukan hasil leveling', { timeout: 15000 })
  await expect(page.getByTestId('profile-chart').or(page.getByTestId('profile-empty'))).toBeVisible({ timeout: 15000 })
  expect(errors).toEqual([])
})

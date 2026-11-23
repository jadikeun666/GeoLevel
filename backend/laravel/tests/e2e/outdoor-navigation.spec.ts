import { test, expect } from '@playwright/test'
import { loginAs } from './helpers'

// Needs: php artisan db:seed --class=OutdoorDemoSeeder. Exact numbers are covered by tests/js/navigation.test.mjs.
test.use({ permissions: ['geolocation'], geolocation: { latitude: -6.2, longitude: 106.8, accuracy: 5 } })

test('navigasi: pilih waypoint, mulai, jarak dan arah muncul, berhenti', async ({ page }) => {
  await loginAs(page, 'outdoordemo@geolevel.com', 'password')
  const errors: string[] = []
  page.on('pageerror', (e) => errors.push(e.message))
  await page.goto('/outdoor')

  const target = page.getByTestId('nav-target')
  await expect(target).toBeVisible({ timeout: 15000 })
  await target.selectOption({ index: 1 })
  await page.getByTestId('nav-start').click()

  await expect(page.getByTestId('nav-distance')).toHaveText(/\d.* (m|km)$/, { timeout: 15000 })
  await expect(page.getByTestId('nav-bearing')).toContainText('°')

  await page.getByTestId('nav-stop').click()
  await expect(page.getByTestId('nav-start')).toBeVisible()
  expect(errors).toEqual([])
})

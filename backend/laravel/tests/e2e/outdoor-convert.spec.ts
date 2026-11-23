import { test, expect } from '@playwright/test'
import { loginAs } from './helpers'

// Needs: php artisan db:seed --class=OutdoorDemoSeeder. Reference values come from PROJ/GeoTrans (docs 19.1).
test('konverter: DD Jakarta -> MGRS dan UTM dari PROJ, lalu UTM -> DD', async ({ page }) => {
  await loginAs(page, 'outdoordemo@geolevel.com', 'password')
  await page.goto('/outdoor')

  await page.getByTestId('conv-lat').fill('-6.2088')
  await page.getByTestId('conv-lng').fill('106.8456')
  await page.getByTestId('conv-submit').click()
  await expect(page.getByTestId('conv-mgrs')).toHaveText('48MYU0420713358', { timeout: 15000 })
  await expect(page.getByTestId('conv-utm')).toHaveText('48S 704207.169 9313358.339')
  await expect(page.getByTestId('conv-dms')).toContainText('6°12\'31.68"S')

  await page.getByTestId('conv-mode-utm').click()
  await page.getByTestId('conv-zone').fill('55')
  await page.getByTestId('conv-easting').fill('500000')
  await page.getByTestId('conv-northing').fill('0')
  await page.getByTestId('conv-submit').click()
  await expect(page.getByText('di luar jangkauan')).toBeVisible({ timeout: 15000 })
})

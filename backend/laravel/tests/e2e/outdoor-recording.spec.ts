import { test, expect, Page } from '@playwright/test'
import { loginAs } from './helpers'

// Needs: php artisan db:seed --class=OutdoorDemoSeeder
// Mock GPS steps are 0.0001 deg of latitude (~11.1 m > record_min_distance_m 5) and
// 2.3 s apart (> record_min_interval_s 2), so every step passes the client filter.
const EMAIL = 'outdoordemo@geolevel.com'

test.use({
  permissions: ['geolocation'],
  geolocation: { latitude: -6.2, longitude: 106.8, accuracy: 5 },
})

async function openRecorderClean(page: Page) {
  await page.goto('/outdoor')
  const start = page.getByTestId('recorder-start')
  const recover = page.getByTestId('recorder-recover')
  await start.or(recover).first().waitFor({ timeout: 15000 })
  if (await recover.isVisible()) {
    await page.getByTestId('recorder-finish-active').click()
    await expect(start).toBeVisible({ timeout: 15000 })
  }
}

async function step(page: Page, context: any, index: number) {
  await context.setGeolocation({ latitude: -6.2 + index * 0.0001, longitude: 106.8, accuracy: 5 })
  await expect(page.getByTestId('recorder-count')).toHaveText(String(index + 1), { timeout: 10000 })
  await page.waitForTimeout(2300)
}

test.describe('Outdoor: rekam live', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, EMAIL, 'password')
  })

  test('mulai, 4 posisi lolos filter, selesai: track tampil dengan 4 titik', async ({ page, context }) => {
    test.setTimeout(90000)
    await openRecorderClean(page)

    const name = `E2E Rekam ${Date.now()}`
    await page.getByTestId('recorder-name').fill(name)
    await page.getByTestId('recorder-start').click()
    await expect(page.getByTestId('recorder-state')).toHaveText('Merekam', { timeout: 10000 })

    for (let i = 0; i < 4; i++) await step(page, context, i)

    await page.getByTestId('recorder-finish').click()
    await expect(page.getByTestId('recorder-message')).toContainText('diselesaikan', { timeout: 15000 })
    await expect(page.getByTestId('recorder-start')).toBeVisible()
    await expect(page.locator('li', { hasText: name })).toContainText('4 titik', { timeout: 15000 })
  })

  test('balasan batch hilang: retry dengan UUID sama tidak menggandakan titik', async ({ page, context }) => {
    test.setTimeout(90000)
    await openRecorderClean(page)

    let calls = 0
    let dropped = false
    await page.route('**/outdoor/tracks/*/points', async (route) => {
      calls++
      if (!dropped) {
        dropped = true
        await route.fetch()          // server stores the batch...
        await route.abort('failed')  // ...but the client never sees the reply
      } else {
        await route.continue()
      }
    })

    const name = `E2E Retry ${Date.now()}`
    await page.getByTestId('recorder-name').fill(name)
    await page.getByTestId('recorder-start').click()
    await expect(page.getByTestId('recorder-state')).toHaveText('Merekam', { timeout: 10000 })

    for (let i = 0; i < 3; i++) await step(page, context, i)

    await page.getByTestId('recorder-finish').click()
    await expect(page.getByTestId('recorder-message')).toContainText('Belum semua titik terkirim', { timeout: 15000 })

    // The automatic retry (2 s) resends the SAME batch; server answers duplicate=true.
    await expect(page.getByTestId('recorder-server-count')).toHaveText('3', { timeout: 15000 })
    await expect(page.getByTestId('recorder-queued')).toHaveText('0', { timeout: 15000 })
    expect(calls).toBeGreaterThanOrEqual(2)

    await page.getByTestId('recorder-finish').click()
    await expect(page.getByTestId('recorder-message')).toContainText('diselesaikan', { timeout: 15000 })
    await expect(page.locator('li', { hasText: name })).toContainText('3 titik', { timeout: 15000 })
  })
})

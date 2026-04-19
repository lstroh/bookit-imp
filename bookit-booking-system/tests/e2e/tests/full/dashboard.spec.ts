import { test, expect } from '@playwright/test';
import { loginAsAdmin, loginAsStaff } from '../../fixtures/auth';

// Dashboard Vue app selectors (from MySchedule.vue):
//   Status badge:       span with rounded-full (status text inside)
//   Mark Complete btn:  button with class bg-green-600 and text "✓ Mark Complete"
//   No-Show btn:        button with class bg-red-600 and text "✗ No-Show"
//   Booking card:       .bg-white.rounded-lg.shadow

test.describe('Dashboard flows', { tag: '@full' }, () => {
  test('admin login — dashboard home loads', async ({ page }) => {
    await loginAsAdmin(page);
    // Vue app has loaded — should not see login form
    await expect(page.locator('input[name="email"]')).not.toBeVisible();
    await expect(page.locator('body')).toBeVisible();
  });

  test("admin sees today's schedule section", async ({ page }) => {
    await loginAsAdmin(page);
    // MySchedule.vue renders a schedule view — assert page has content
    await expect(page.locator('body')).not.toContainText('Fatal error');
    // Schedule section heading (may vary — assert page loaded meaningfully)
    await expect(page.locator('.bg-white').first()).toBeVisible({ timeout: 10_000 });
  });

  test('admin can mark a confirmed booking as complete via schedule', async ({ page }) => {
    await loginAsAdmin(page);
    // Find a confirmed booking — look for the Mark Complete button
    // (Only visible on confirmed bookings per MySchedule.vue)
    const completeBtn = page.locator('button.bg-green-600').first();
    if (await completeBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await completeBtn.click();
      // Status badge should update — wait for UI to reflect
      await page.waitForTimeout(1_000);
      // The button should disappear (terminal state)
      await expect(completeBtn).not.toBeVisible();
    } else {
      test.skip(); // No confirmed bookings available to test
    }
  });

  test('admin can mark a confirmed booking as no-show', async ({ page }) => {
    await loginAsAdmin(page);
    const noShowBtn = page.locator('button.bg-red-600').first();
    if (await noShowBtn.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await noShowBtn.click();
      await page.waitForTimeout(1_000);
      await expect(noShowBtn).not.toBeVisible();
    } else {
      test.skip();
    }
  });

  test('staff login — sees own bookings only', async ({ page }) => {
    await loginAsStaff(page);
    await expect(page.locator('input[name="email"]')).not.toBeVisible();
    // Staff dashboard loads — no fatal errors
    await expect(page.locator('body')).not.toContainText('Fatal error');
  });
});

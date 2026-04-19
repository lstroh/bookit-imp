import { Page } from '@playwright/test';
import { clearMailpit } from './mailpit';

const TEST_EMAIL = process.env.BOOKIT_TEST_CUSTOMER_EMAIL || 'testcustomer@bookit-e2e.local';

/**
 * Complete the booking wizard Steps 1–4.
 * Selects service and staff by name from env vars.
 * Returns the test email address used (for Mailpit queries).
 *
 * Requires in .env.test.local:
 *   BOOKIT_TEST_SERVICE_NAME — exact name of service to select
 *   BOOKIT_TEST_STAFF_NAME   — exact name of staff member to select
 *   BOOKIT_TEST_CUSTOMER_EMAIL — email to use in contact form
 *
 * Note: Step 1 persists the service via JS before Continue is enabled. Step 2 calls
 * POST /bookit/v1/staff/select, which saves staff and sets current_step to 3, then
 * booking-wizard-v2.js reloads — so Step 3 is reached without clicking Continue again.
 */
export async function completeWizardSteps1To4(page: Page): Promise<string> {
  await clearMailpit();
  await page.goto('/book-v2/');

  // --- Step 1: Select service by name ---
  const serviceName = process.env.BOOKIT_TEST_SERVICE_NAME;
  if (!serviceName) throw new Error('BOOKIT_TEST_SERVICE_NAME is not set in .env.test.local');

  await page.waitForSelector('.bookit-v2-service-card');

  const escaped = serviceName.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  let serviceCard = page.locator(`.bookit-v2-service-card[data-service-name="${escaped}"]`);
  if (await serviceCard.count() === 0) {
    const nameRe = new RegExp(`^${serviceName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`);
    serviceCard = page
      .locator('.bookit-v2-service-card')
      .filter({ has: page.locator('.bookit-v2-service-name', { hasText: nameRe }) });
  }
  if (await serviceCard.count() === 0) {
    throw new Error(
      `Service card not found for BOOKIT_TEST_SERVICE_NAME="${serviceName}". ` +
        `Check the service exists and is active on the local site.`
    );
  }
  await serviceCard.first().click();
  await page.waitForFunction(() => {
    const btn = document.querySelector<HTMLButtonElement>('#bookit-v2-continue');
    return !!btn && !btn.disabled;
  });
  await page.locator('#bookit-v2-continue').click();

  // --- Step 2: Select staff by name ---
  const staffName = process.env.BOOKIT_TEST_STAFF_NAME;
  if (!staffName) throw new Error('BOOKIT_TEST_STAFF_NAME is not set in .env.test.local');

  await page.waitForSelector('.bookit-v2-staff-row, .bookit-v2-staff-card');

  const staffRow = page
    .locator(
      '.bookit-v2-staff-row:not(.bookit-v2-staff-row--unavailable), ' +
        '.bookit-v2-staff-card:not(.bookit-v2-staff-card--unavailable)'
    )
    .filter({ hasText: staffName });

  if (await staffRow.count() === 0) {
    throw new Error(
      `Staff row not found for BOOKIT_TEST_STAFF_NAME="${staffName}". ` +
        `Check the staff member exists, is active, and is assigned to ` +
        `service "${serviceName}" on the local site.`
    );
  }

  // booking-wizard-v2.js reloads the page after a successful staff/select call; the response body
  // is often unreadable after unload, so wait for navigation instead of parsing JSON.
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'load', timeout: 20_000 }),
    staffRow.first().click(),
  ]).catch(() => {
    throw new Error(
      `Staff selection did not complete for BOOKIT_TEST_STAFF_NAME="${staffName}". ` +
        `No page reload followed the click (staff/select may have failed — check nonce, ` +
        `assignment to service "${serviceName}", and browser console).`
    );
  });
  // staff/select success triggers a full reload onto Step 3 — do not click Continue here (race with advanceStep).

  // --- Step 3: Navigate calendar to find an available date, then pick a slot ---
  await page.waitForSelector('.bookit-v2-calendar', { timeout: 15_000 });

  let slotPicked = false;
  for (let month = 0; month < 3; month++) {
    const availableDays = page.locator('.bookit-v2-day--available');
    const dayCount = await availableDays.count();
    const maxTry = Math.min(dayCount, 8);
    for (let i = 0; i < maxTry; i++) {
      await availableDays.nth(i).click();
      const slot = page.locator('.bookit-v2-slot--available').first();
      if (await slot.isVisible({ timeout: 2_500 }).catch(() => false)) {
        await slot.click();
        slotPicked = true;
        break;
      }
    }
    if (slotPicked) {
      break;
    }
    // Full-page navigation via PHP-generated month URL (see booking-wizard-v2-step-3.php).
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'load', timeout: 15_000 }),
      page.evaluate(() => {
        const cal = document.querySelector('.bookit-v2-calendar');
        if (!cal) {
          throw new Error('Missing .bookit-v2-calendar (cannot advance month).');
        }
        const links = Array.from(cal.querySelectorAll<HTMLAnchorElement>('a[href*="month"]'));
        if (links.length === 0) {
          throw new Error('No month navigation <a> links found inside .bookit-v2-calendar.');
        }
        links[links.length - 1].click();
      }),
    ]).catch((err: unknown) => {
      const msg = err instanceof Error ? err.message : String(err);
      throw new Error(`Next month navigation failed: ${msg}`);
    });
    await page.waitForTimeout(600);
  }

  if (!slotPicked) {
    throw new Error(
      `No bookable time slots found in the next 3 months for ` +
        `staff "${staffName}" / service "${serviceName}". ` +
        `Go to Dashboard → Staff → ${staffName} → Working Hours and add availability ` +
        `(and confirm the service is assigned to this staff member).`
    );
  }

  await page.waitForFunction(() => {
    const btn = document.querySelector<HTMLButtonElement>('#bookit-v2-continue');
    return !!btn && !btn.disabled;
  });
  await page.locator('#bookit-v2-continue').click();

  // --- Step 4: Fill contact form ---
  await page.waitForSelector('#bookit-contact-form');
  await page.fill('#first-name', 'Test');
  await page.fill('#last-name', 'Bookit');
  await page.fill('#email', TEST_EMAIL);
  await page.fill('#phone', '07700900000');

  // Check cooling-off waiver if visible (shown for bookings within 14 days)
  if (await page.locator('#cooling-off-waiver-group').isVisible()) {
    await page.check('#cooling-off-waiver');
  }

  // Submit Step 4
  await page.locator('#bookit-contact-form button[type="submit"].bookit-v2-cta-btn').click();
  await page.waitForSelector('#bookit-v2-cta-btn');

  return TEST_EMAIL;
}

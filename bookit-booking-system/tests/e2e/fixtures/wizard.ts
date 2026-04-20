import { Page } from '@playwright/test';
import { clearMailpit } from './mailpit';

const TEST_EMAIL = process.env.BOOKIT_TEST_CUSTOMER_EMAIL || 'testcustomer@bookit-e2e.local';

/**
 * Complete the booking wizard Steps 1–4.
 *
 * Selects service and staff by name from env vars:
 *   BOOKIT_TEST_SERVICE_NAME — exact name of service card to select
 *   BOOKIT_TEST_STAFF_NAME   — exact name of staff member to select
 *
 * KEY BEHAVIOURS:
 * - Step 2: clicking a staff row calls bookit/v1/staff/select which triggers
 *   window.location.reload() on success — no Continue click needed or wanted.
 * - Step 3: month nav arrows are <a href> links causing full page loads —
 *   must use waitForNavigation alongside the click.
 */
export async function completeWizardSteps1To4(page: Page): Promise<string> {
  await clearMailpit();
  await page.goto('/book-v2/');

  // -----------------------------------------------------------------------
  // Step 1: Select service by name
  // -----------------------------------------------------------------------
  const serviceName = process.env.BOOKIT_TEST_SERVICE_NAME;
  if (!serviceName) {
    throw new Error('BOOKIT_TEST_SERVICE_NAME is not set in .env.test.local');
  }

  await page.waitForSelector('.bookit-v2-service-card');

  const serviceCard = page.locator(
    `.bookit-v2-service-card[data-service-name="${serviceName}"]`
  );
  if ((await serviceCard.count()) === 0) {
    throw new Error(
      `Service card not found for BOOKIT_TEST_SERVICE_NAME="${serviceName}". ` +
      `Check the service exists and is active on the local site.`
    );
  }
  await serviceCard.first().click();

  // Wait for Continue to be enabled, then click it (Step 1 does need Continue)
  await page.waitForFunction(() => {
    const btn = document.querySelector<HTMLButtonElement>('#bookit-v2-continue');
    return btn !== null && !btn.disabled;
  });
  await page.locator('#bookit-v2-continue').click();

  // -----------------------------------------------------------------------
  // Step 2: Select staff by name — page reloads automatically on success
  // DO NOT click Continue after staff selection
  // -----------------------------------------------------------------------
  const staffName = process.env.BOOKIT_TEST_STAFF_NAME;
  if (!staffName) {
    throw new Error('BOOKIT_TEST_STAFF_NAME is not set in .env.test.local');
  }

  await page.waitForSelector('.bookit-v2-staff-row, .bookit-v2-staff-card');

  const staffRow = page
    .locator(
      '.bookit-v2-staff-row:not(.bookit-v2-staff-row--unavailable), ' +
        '.bookit-v2-staff-card:not(.bookit-v2-staff-card--unavailable)'
    )
    .filter({ hasText: staffName });

  if ((await staffRow.count()) === 0) {
    throw new Error(
      `Staff row not found for BOOKIT_TEST_STAFF_NAME="${staffName}". ` +
      `Check the staff member exists, is active, and is assigned to ` +
      `service "${serviceName}" on the local site.`
    );
  }

  // Click staff row and wait for the automatic page reload to Step 3.
  // booking-wizard-v2.js calls window.location.reload() after staff/select succeeds.
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'load', timeout: 20_000 }),
    staffRow.first().click(),
  ]);
  // Page is now on Step 3. Do NOT click Continue here.

  // -----------------------------------------------------------------------
  // Step 3: Find an available day (navigate months if needed), pick a slot
  // Month nav arrows are <a href> links — must waitForNavigation with click
  // -----------------------------------------------------------------------
  await page.waitForSelector('.bookit-v2-calendar', { timeout: 15_000 });

  let slotPicked = false;

  for (let month = 0; month < 3; month++) {
    // Try each available day in this month until one has slots
    const availableDays = page.locator('.bookit-v2-day--available');
    const dayCount = await availableDays.count();

    for (let i = 0; i < Math.min(dayCount, 8); i++) {
      const dayBtn = availableDays.nth(i);
      const clickedDate = await dayBtn.getAttribute('data-date');
      await dayBtn.click();

      // Day click posts current_step/date and regenerates the session cookie.
      // Wait until the server session reflects the clicked date before continuing,
      // otherwise subsequent requests (or month navigation) can use a stale session.
      if (clickedDate) {
        let datePersisted = false;
        for (let attempt = 0; attempt < 10; attempt++) {
          const res = await page.request.get('/wp-json/bookit/v1/wizard/session');
          const json = (await res.json().catch(() => null)) as null | {
            success?: boolean;
            data?: { date?: string };
          };
          if (json?.data?.date === clickedDate) {
            datePersisted = true;
            break;
          }
          await page.waitForTimeout(200);
        }
        if (!datePersisted) {
          throw new Error(`Wizard session did not persist selected date "${clickedDate}".`);
        }
      }
      // Slots load asynchronously via fetch after a day click
      const slotVisible = await page
        .locator('.bookit-v2-slot--available')
        .first()
        .isVisible({ timeout: 3_000 })
        .catch(() => false);

      if (slotVisible) {
        await page.locator('.bookit-v2-slot--available').first().click();
        slotPicked = true;
        break;
      }
      // This day has no slots — try the next available day
    }

    if (slotPicked) break;

    // No slots found this month — navigate to next month via <a href> link
    // Must waitForNavigation because clicking the link causes a full page load
    if (month < 2) {
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'load', timeout: 15_000 }),
        page.locator('.bookit-v2-calendar-nav').last().click(),
      ]);
      await page.waitForSelector('.bookit-v2-calendar', { timeout: 10_000 });
    }
  }

  if (!slotPicked) {
    throw new Error(
      `No bookable time slots found in the next 3 months for ` +
      `staff "${staffName}" / service "${serviceName}". ` +
      `Go to Dashboard → Staff → ${staffName} → Working Hours and confirm ` +
      `availability is configured and the service is assigned to this staff member.`
    );
  }

  // Continue is enabled once a slot is selected
  await page.waitForFunction(() => {
    const btn = document.querySelector<HTMLButtonElement>('#bookit-v2-continue');
    return btn !== null && !btn.disabled;
  });

  // Ensure the slot POST has actually persisted date/time in the *current* session
  // before we advance to Step 4. The backend regenerates session ID cookies on
  // current_step updates, so two rapid POSTs can land in different sessions.
  let hasDateTime = false;
  for (let attempt = 0; attempt < 10; attempt++) {
    const res = await page.request.get('/wp-json/bookit/v1/wizard/session');
    const json = (await res.json().catch(() => null)) as null | {
      success?: boolean;
      data?: { date?: string; time?: string };
    };
    const date = json?.data?.date;
    const time = json?.data?.time;
    if (date && time) {
      hasDateTime = true;
      break;
    }
    await page.waitForTimeout(200);
  }

  if (!hasDateTime) {
    throw new Error('Slot selection did not persist date/time in wizard session before continuing.');
  }

  await page.locator('#bookit-v2-continue').click();

  // -----------------------------------------------------------------------
  // Step 4: Fill contact form
  // -----------------------------------------------------------------------
  await page.waitForSelector('#bookit-contact-form', { timeout: 15_000 });
  await page.fill('#first-name', 'Test');
  await page.fill('#last-name', 'Bookit');
  await page.fill('#email', TEST_EMAIL);
  await page.fill('#phone', '07700900000');

  // Check cooling-off waiver if visible (only shown for near-term bookings)
  if (await page.locator('#cooling-off-waiver-group').isVisible()) {
    await page.check('#cooling-off-waiver');
  }

  // Submit Step 4 — triggers session save and advances to Step 5
  await page.locator(
    '#bookit-contact-form button[type="submit"].bookit-v2-cta-btn'
  ).click();

  // Wait for Step 5 CTA to confirm we've advanced
  await page.waitForSelector('#bookit-v2-cta-btn', { timeout: 15_000 });

  return TEST_EMAIL;
}

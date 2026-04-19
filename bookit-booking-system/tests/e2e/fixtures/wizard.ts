import { Page } from '@playwright/test';
import { clearMailpit } from './mailpit';

const TEST_EMAIL = process.env.BOOKIT_TEST_CUSTOMER_EMAIL || 'testcustomer@bookit-e2e.local';

// SELECTOR REFERENCE (all sourced from PHP templates — do not change):
//
// Step 1 (booking-wizard-v2-step-1.php):
//   Service card:       .bookit-v2-service-card            (data-service-id, data-service-name)
//   Continue button:    #bookit-v2-continue
//
// Step 2 (booking-wizard-v2-step-2.php):
//   Staff (≤3 staff):  .bookit-v2-staff-row               (available = no --unavailable class)
//   Staff (4+ staff):  .bookit-v2-staff-card              (available = no --unavailable class)
//
// Step 3 (booking-wizard-v2-step-3.php):
//   Calendar day:       .bookit-v2-day--available          (button, data-date="YYYY-MM-DD")
//   Time slot:          .bookit-v2-slot--available         (button, data-time="HH:MM:SS")
//   Continue button:    #bookit-v2-continue
//
// Step 4 (booking-wizard-v2-step-4.php):
//   First name:         #first-name          (name="first_name")
//   Last name:          #last-name           (name="last_name")
//   Email:              #email               (name="email")
//   Phone:              #phone               (name="phone")
//   Cooling-off waiver: #cooling-off-waiver  (conditional — only shown for near-term bookings)
//   Waiver container:   #cooling-off-waiver-group
//   Submit (Step 4):    #bookit-contact-form button[type="submit"].bookit-v2-cta-btn
//
// Step 5 (booking-wizard-v2-step-5.php):
//   Pay in person row:  #bookit-v2-pay-person              (data-value="person")
//   Pay by card radio:  input[name="bookit_v2_payment_choice"][value="card"]
//   CTA button:         #bookit-v2-cta-btn
//   After POA confirm:  redirects to /booking-confirmed-v2/
//   Booking reference:  text matching /BK-\w+/

export async function completeWizardSteps1To4(page: Page): Promise<string> {
  await clearMailpit();
  await page.goto('/book-v2/');

  // Step 1: Select first service
  await page.waitForSelector('.bookit-v2-service-card');
  await page.locator('.bookit-v2-service-card').first().click();
  await page.locator('#bookit-v2-continue').click();

  // Step 2: Select first available staff
  // Staff renders as .bookit-v2-staff-row (≤3 staff) or .bookit-v2-staff-card (4+ staff)
  await page.waitForSelector(
    '.bookit-v2-staff-row:not(.bookit-v2-staff-row--unavailable), .bookit-v2-staff-card:not(.bookit-v2-staff-card--unavailable)'
  );
  await page
    .locator(
      '.bookit-v2-staff-row:not(.bookit-v2-staff-row--unavailable), .bookit-v2-staff-card:not(.bookit-v2-staff-card--unavailable)'
    )
    .first()
    .click();
  await page.locator('#bookit-v2-continue').click();

  // Step 3: Click first available calendar date, then first available time slot
  await page.waitForSelector('.bookit-v2-day--available');
  await page.locator('.bookit-v2-day--available').first().click();
  await page.waitForSelector('.bookit-v2-slot--available', { timeout: 10_000 });
  await page.locator('.bookit-v2-slot--available').first().click();
  await page.locator('#bookit-v2-continue').click();

  // Step 4: Fill contact form
  await page.waitForSelector('#bookit-contact-form');
  await page.fill('#first-name', 'Test');
  await page.fill('#last-name', 'Bookit');
  await page.fill('#email', TEST_EMAIL);
  await page.fill('#phone', '07700900000');

  // Check cooling-off waiver if visible
  if (await page.locator('#cooling-off-waiver-group').isVisible()) {
    await page.check('#cooling-off-waiver');
  }

  // Submit Step 4
  await page.locator('#bookit-contact-form button[type="submit"].bookit-v2-cta-btn').click();
  await page.waitForSelector('#bookit-v2-cta-btn');

  return TEST_EMAIL;
}

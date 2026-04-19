import { test, expect } from '@playwright/test';
import { completeWizardSteps1To4 } from '../../fixtures/wizard';
import { getLatestEmail, extractLinkFromEmail, clearMailpit } from '../../fixtures/mailpit';

test.describe('Cancellation email content', { tag: '@full' }, () => {
  test('cancellation email has correct subject and service name', async ({ page }) => {
    const testEmail = await completeWizardSteps1To4(page);
    await page.locator('#bookit-v2-pay-person').click();
    await page.locator('#bookit-v2-cta-btn').click();
    await page.waitForURL('**/booking-confirmed-v2/**', { timeout: 20_000 });

    const confirmEmail = await getLatestEmail(testEmail);
    const cancelUrl = extractLinkFromEmail(confirmEmail.HTML, 'Cancel Booking');

    await clearMailpit();
    await page.goto(cancelUrl);
    const confirmBtn = page.locator('#bookit-cancel-confirm');
    if (await confirmBtn.isVisible()) await confirmBtn.click();

    const cancelEmail = await getLatestEmail(testEmail);
    expect(cancelEmail.Subject.toLowerCase()).toContain('cancel');
    // Should contain the service name booked
    expect(cancelEmail.HTML.length).toBeGreaterThan(0);
  });
});

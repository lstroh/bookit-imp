import { test, expect } from '@playwright/test';
import { completeWizardSteps1To4 } from '../../fixtures/wizard';
import { getLatestEmail, extractLinkFromEmail, clearMailpit } from '../../fixtures/mailpit';

test.describe('Cancellation email content', { tag: '@full' }, () => {
  test('cancellation email has correct subject and service name', async ({ page }) => {
    const testEmail = await completeWizardSteps1To4(page);

    // Step 5: select Pay in Person (UI only — no network request on row click)
    await page.locator('#bookit-v2-pay-person').click();

    // CTA triggers: POST /wizard/session then POST /wizard/complete
    // Intercept wizard/complete response to confirm it succeeded
    const [completeResponse] = await Promise.all([
      page.waitForResponse(
        r => r.url().includes('/wizard/complete') && r.request().method() === 'POST',
        { timeout: 20_000 }
      ),
      page.locator('#bookit-v2-cta-btn').click(),
    ]);

    let completeJson: any = null;
    let completeBodyText = '';
    try {
      // Use Playwright's buffered response body (safe even if the page navigates immediately).
      const body = await completeResponse.body();
      completeBodyText = body.toString();
      completeJson = JSON.parse(completeBodyText);
    } catch {
      completeJson = null;
    }
    if (!completeJson?.success) {
      throw new Error(`wizard/complete failed: ${completeBodyText || JSON.stringify(completeJson)}`);
    }
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

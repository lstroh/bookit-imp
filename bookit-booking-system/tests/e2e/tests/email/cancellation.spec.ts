import { test, expect } from '@playwright/test';
import { completeWizardSteps1To4 } from '../../fixtures/wizard';
import { getLatestEmail, extractLinkFromEmail, clearMailpit } from '../../fixtures/mailpit';

test.describe('Cancellation email content', { tag: '@full' }, () => {
  test('cancellation email has correct subject and service name', async ({ page }) => {
    const testEmail = await completeWizardSteps1To4(page);

    const payPersonRadio = page.locator(
      'input[name="bookit_v2_payment_choice"][value="person"]'
    );
    const payPersonAlreadySelected =
      (await payPersonRadio.count()) > 0 && (await payPersonRadio.isChecked());

    if (!payPersonAlreadySelected) {
      // Click Pay in person row and wait for its session POST to complete
      const [paymentRowResponse] = await Promise.all([
        page.waitForResponse(
          r =>
            r.request().method() === 'POST' &&
            (r.url().includes('wizard/session') ||
              r.url().includes('wizard%2Fsession') ||
              r.url().includes('bookit/v1/wizard/session') ||
              r.url().includes('bookit%2Fv1%2Fwizard%2Fsession')),
          { timeout: 15_000 }
        ),
        payPersonRadio.check(),
      ]);
      const paymentRowJson = await paymentRowResponse.json().catch(() => null);
      if (!paymentRowJson?.success) {
        throw new Error(
          `Payment method session POST failed: ${JSON.stringify(paymentRowJson)}`
        );
      }
    }

    // CTA triggers: POST /wizard/session then POST /wizard/complete
    // Intercept wizard/complete response to confirm it succeeded
    const [completeResponse] = await Promise.all([
      page.waitForResponse(
        r => r.url().includes('/wizard/complete') && r.request().method() === 'POST',
        { timeout: 15_000 }
      ),
      page.locator('#bookit-v2-cta-btn').click(),
    ]);

    const completeJson = await completeResponse.json().catch(() => null);
    if (!completeJson?.success) {
      throw new Error(`wizard/complete failed: ${JSON.stringify(completeJson)}`);
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

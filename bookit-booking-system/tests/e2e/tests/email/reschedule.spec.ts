import { test, expect } from '@playwright/test';
import { completeWizardSteps1To4 } from '../../fixtures/wizard';
import { getLatestEmail, extractLinkFromEmail, clearMailpit } from '../../fixtures/mailpit';

test.describe('Reschedule email content', { tag: '@full' }, () => {
  test('reschedule email has correct subject and action links', async ({ page }) => {
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
    const rescheduleUrl = extractLinkFromEmail(confirmEmail.HTML, 'Reschedule');

    await clearMailpit();
    await page.goto(rescheduleUrl);

    await page.waitForSelector('.bookit-v2-day--available', { timeout: 10_000 });
    const dates = page.locator('.bookit-v2-day--available');
    const count = await dates.count();
    await dates.nth(count > 1 ? 1 : 0).click();
    await page.waitForSelector('.bookit-v2-slot--available', { timeout: 10_000 });
    await page.locator('.bookit-v2-slot--available').first().click();
    const confirmBtn = page.locator('#bookit-reschedule-confirm');
    if (await confirmBtn.isVisible()) {
      await expect(confirmBtn).toBeEnabled({ timeout: 10_000 });
      await confirmBtn.click();
    }

    const rescheduleEmail = await getLatestEmail(testEmail);
    expect(rescheduleEmail.Subject.toLowerCase()).toContain('reschedul');
    expect(rescheduleEmail.HTML.toLowerCase()).toContain('cancel');
    expect(rescheduleEmail.HTML.toLowerCase()).toContain('reschedule');
  });
});

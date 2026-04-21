import { test, expect } from '@playwright/test';
import { completeWizardSteps1To4 } from '../../fixtures/wizard';
import { getLatestEmail, extractLinkFromEmail, clearMailpit } from '../../fixtures/mailpit';

test.describe('Reschedule email content', { tag: '@full' }, () => {
  test('reschedule email has correct subject and action links', async ({ page }) => {
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

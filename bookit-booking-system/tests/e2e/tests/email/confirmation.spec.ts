import { test, expect } from '@playwright/test';
import { completeWizardSteps1To4 } from '../../fixtures/wizard';
import { getLatestEmail } from '../../fixtures/mailpit';

test.describe('Confirmation email content', { tag: '@full' }, () => {
  test('confirmation email has correct subject, booking ref, and action links', async ({ page }) => {
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

    // Read body immediately — page may navigate before .json() resolves
    const responseBody = await completeResponse.text().catch(() => '{}');
    let completeJson: any = null;
    try {
      completeJson = JSON.parse(responseBody);
    } catch {
      completeJson = null;
    }
    if (!completeJson?.success) {
      throw new Error(`wizard/complete failed: ${responseBody}`);
    }
    await page.waitForURL('**/booking-confirmed-v2/**', { timeout: 20_000 });

    const email = await getLatestEmail(testEmail);

    // Subject
    expect(email.Subject.toLowerCase()).toContain('confirmed');
    // Booking reference (BK- prefix from confirmation template)
    expect(email.HTML).toMatch(/BK[\d-]/);
    // Magic links present
    expect(email.HTML.toLowerCase()).toContain('cancel');
    expect(email.HTML.toLowerCase()).toContain('reschedule');
    // Add to calendar
    expect(email.HTML.toLowerCase()).toContain('calendar');
  });
});

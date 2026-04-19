import { test, expect } from '@playwright/test';
import { completeWizardSteps1To4 } from '../../fixtures/wizard';
import { getLatestEmail } from '../../fixtures/mailpit';

test.describe('Confirmation email content', { tag: '@full' }, () => {
  test('confirmation email has correct subject, booking ref, and action links', async ({ page }) => {
    const testEmail = await completeWizardSteps1To4(page);
    await page.locator('#bookit-v2-pay-person').click();
    await page.locator('#bookit-v2-cta-btn').click();
    await page.waitForURL('**/booking-confirmed-v2/**', { timeout: 20_000 });

    const email = await getLatestEmail(testEmail);

    // Subject
    expect(email.Subject.toLowerCase()).toContain('confirmed');
    // Booking reference (BK- prefix from confirmation template)
    expect(email.HTML).toMatch(/BK-/);
    // Magic links present
    expect(email.HTML.toLowerCase()).toContain('cancel');
    expect(email.HTML.toLowerCase()).toContain('reschedule');
    // Add to calendar
    expect(email.HTML.toLowerCase()).toContain('calendar');
  });
});

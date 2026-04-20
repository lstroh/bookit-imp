import { test, expect } from '@playwright/test';
import { completeWizardSteps1To4 } from '../../fixtures/wizard';
import { getLatestEmail } from '../../fixtures/mailpit';

// Step 5 selectors from booking-wizard-v2-step-5.php:
//   Pay in person row:  #bookit-v2-pay-person  (data-value="person")
//   CTA button:         #bookit-v2-cta-btn
//   After confirm:      redirects to /booking-confirmed-v2/?...
//   Booking ref:        text matching /BK[\d-]/

test.describe('Full booking — Pay on Arrival', { tag: '@full' }, () => {
  test('completes wizard Steps 1–5 POA, shows confirmation, delivers email', async ({ page }) => {
    const testEmail = await completeWizardSteps1To4(page);

    // Step 5: select Pay in Person
    await page.locator('#bookit-v2-pay-person').click();
    // CTA label updates to "Confirm booking" — click it
    await page.locator('#bookit-v2-cta-btn').click();

    // Assert confirmation page loaded
    await page.waitForURL('**/booking-confirmed-v2/**', { timeout: 20_000 });
    // Booking reference format is BK- (from booking-confirmed-v2.php)
    await expect(page.locator('body')).toContainText(/BK[\d-]/);

    // Assert confirmation email in Mailpit
    const email = await getLatestEmail(testEmail);
    expect(email.Subject.toLowerCase()).toContain('confirmed');
    expect(email.HTML).toMatch(/BK[\d-]/);
    // Email must contain Cancel and Reschedule links (magic link)
    expect(email.HTML.toLowerCase()).toContain('cancel');
    expect(email.HTML.toLowerCase()).toContain('reschedule');
    // Add to calendar button
    expect(email.HTML.toLowerCase()).toContain('calendar');
  });
});

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
    let testEmail: string | undefined;
    let lastCompleteJson: any = null;

    // Retry the full wizard flow when a slot becomes unavailable due to a prior run.
    // Max 2 retries (3 total attempts).
    for (let attempt = 1; attempt <= 3; attempt++) {
      testEmail = await completeWizardSteps1To4(page);

      // Step 5: select Pay in Person (UI only — no network request on row click)
      await page.locator('#bookit-v2-pay-person').click();

      // CTA click: POST /wizard/session then POST /wizard/complete (chained in JS)
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
      lastCompleteJson = completeJson ?? completeBodyText;

      if (completeJson?.success) {
        break;
      }

      if (completeJson?.code === 'slot_unavailable' && attempt < 3) {
        continue;
      }

      throw new Error(`wizard/complete failed: ${completeBodyText || JSON.stringify(completeJson)}`);
    }

    if (typeof lastCompleteJson === 'object' && lastCompleteJson?.success) {
      // ok
    } else {
      throw new Error(
        `wizard/complete failed after retries: ${
          typeof lastCompleteJson === 'string' ? lastCompleteJson : JSON.stringify(lastCompleteJson)
        }`
      );
    }

    // Assert confirmation page loaded
    await page.waitForURL('**/booking-confirmed-v2/**', { timeout: 20_000 });
    // Booking reference format is BK- (from booking-confirmed-v2.php)
    await expect(page.locator('body')).toContainText(/BK[\d-]/);

    // Assert confirmation email in Mailpit
    const email = await getLatestEmail(testEmail!);
    expect(email.Subject.toLowerCase()).toContain('confirmed');
    expect(email.HTML).toMatch(/BK[\d-]/);
    // Email must contain Cancel and Reschedule links (magic link)
    expect(email.HTML.toLowerCase()).toContain('cancel');
    expect(email.HTML.toLowerCase()).toContain('reschedule');
    // Add to calendar button
    expect(email.HTML.toLowerCase()).toContain('calendar');
  });
});

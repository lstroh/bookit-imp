import { test, expect } from '@playwright/test';
import { completeWizardSteps1To4 } from '../../fixtures/wizard';
import { fillStripeCheckout } from '../../fixtures/stripe';
import { getLatestEmail } from '../../fixtures/mailpit';

// PREREQUISITE: Run in a separate terminal before this test:
//   stripe listen --forward-to http://plugin-test-1.local/wp-json/bookit/v1/stripe/webhook
//
// Step 5 selectors from booking-wizard-v2-step-5.php:
//   Card radio: input[name="bookit_v2_payment_choice"][value="card"]
//   CTA:        #bookit-v2-cta-btn  (label becomes "Pay £X.XX now" when card selected)

test.describe('Full booking — Stripe card payment', { tag: '@full' }, () => {
  test('completes wizard with Stripe, webhook fires, confirmation email delivered', async ({ page }) => {
    const testEmail = await completeWizardSteps1To4(page);

    // Step 5: select card payment
    await page.locator('input[name="bookit_v2_payment_choice"][value="card"]').check();
    // CTA label should update to "Pay £X.XX now"
    await page.locator('#bookit-v2-cta-btn').click();

    // Fill Stripe hosted checkout (headed mode — set in playwright.config.ts for full mode)
    await fillStripeCheckout(page);

    // Confirmation page
    await expect(page.locator('body')).toContainText(/BK-/);

    // Wait for Stripe CLI webhook to fire and email to send (3s buffer)
    await page.waitForTimeout(3_000);

    const email = await getLatestEmail(testEmail);
    expect(email.Subject.toLowerCase()).toContain('confirmed');
    expect(email.HTML).toMatch(/BK-/);
  });
});

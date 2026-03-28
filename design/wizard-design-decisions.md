# Bookit Wizard — Design Decisions

This document records agreed design decisions for the customer-facing booking wizard.
Each step is added as it is designed. These decisions drive the Cursor implementation prompts.

The HTML reference file for each step lives alongside this document in `/design/`.

---

## Global decisions

| Decision | Value |
|---|---|
| Max-width | 680px, centred |
| Default layout | Mobile-first, single column |
| Colour approach | Monochromatic default — white, light greys, dark grey text, one accent colour |
| Accent colour | CSS custom property (`--bookit-color-primary`), overridable by theme |
| Card selected state | Accent border + very subtle background tint. No badge. No filled background. |
| Button hierarchy | Filled accent = primary CTA / Ghost outline = secondary / Text link = tertiary |
| Footer | Sticky at bottom of viewport on mobile — Continue button + Back link |
| Price on service cards | Not shown on Step 1 (price confirmed on Step 5 summary) |

---

## Progress bar

| Decision | Value |
|---|---|
| Style | Step labels across the top, thin 2.5px underline on the active step |
| Labels | Service · Staff · Date & Time · Your Details · Payment |
| Active state | Label in accent colour, underline in accent colour |
| Completed state | Label in muted dark grey, faint accent underline |
| Inactive state | Label in muted grey, no underline |
| Implementation note | Rendered as a shared PHP partial, current step passed as a variable |

---

## Step 1 — Service selection

**Reference file:** `wizard-step1.html`

| Decision | Value |
|---|---|
| Heading | "What would you like to book?" |
| Subheading | "Select a service to get started." |
| Card content | Service name (bold) + duration only. No price. |
| Card grid | CSS `auto-fill, minmax(240px, 1fr)` — 1 column for 1–2 services, 2 columns for 3+ |
| Category grouping | Services grouped by category, uppercase muted label above each group |
| Selected state | Accent colour border + subtle accent tint background. Service name shifts to accent colour. |
| Auto-skip rule | If business has only 1 service: skip Step 1, auto-select, show confirmation banner on Step 2 |
| Back link | Shown but disabled/greyed on Step 1 (no previous step) |
| Continue button | Enabled by default if a service is pre-selected; otherwise enabled on card tap |

---

## Step 2 — Staff selection

**Reference files:** `wizard-step2-list.html` (1–3 staff) · `wizard-step2-grid.html` (4+ staff)

| Decision | Value |
|---|---|
| Heading | "Who would you like?" |
| Subheading | "Choose a team member for your appointment." |
| Service confirmation banner | Slim banner below subheading showing selected service and duration (e.g. "Swedish Massage · 60 min") with a "Change" text link on the right |
| Card content | Avatar circle (initials) + name (bold) + job title + price + one-line bio with "Read more" expand |
| Avatar colour | Deterministic hash of staff member's name — same staff member always gets the same colour |
| Price | Shown on staff cards (unlike Step 1, staff may have different rates) |
| Bio expand | Short one-liner visible by default; "Read more" reveals full bio inline without leaving the step |
| Layout — 1–3 staff | Compact single-column list: small avatar (36px) left, name + title + "Read more" centre, price right |
| Layout — 4+ staff | 2-column card grid. Odd last card spans full width. |
| Selected state | Accent border + subtle tint. Staff name and price shift to accent colour. Avatar gets accent ring. |
| Unavailable staff | Shown but greyed out (opacity 0.5), "No availability this month" replaces price. Not selectable. |
| "Any available team member" | Always shown last, always full-width regardless of layout. Softer background (light grey), no avatar, copy: "We'll match you with the first available person for your chosen time." Same selected state as staff cards. |
| Auto-skip rule — single staff | If business has only 1 staff member: skip Step 2, auto-assign silently |
| Auto-skip rule — hidden staff | If business setting "Staff selection: hidden" is enabled: skip Step 2, auto-assign via No Preference algorithm |
| Back link | Active — returns to Step 1 |
| Continue button | Enabled on card or "Any available" selection |

---

## Step 3 — Date & Time selection

**Reference file:** `wizard-step3.html`

| Decision | Value |
|---|---|
| Heading | "When would you like to come in?" |
| Subheading | "Choose a date and time for your appointment." |
| Confirmation banner | Shows service + duration + staff member (e.g. "Swedish Massage · 60 min · Elena Torres") with a "Change" text link on the right |
| Calendar style | Month grid, clean and minimal. 7 columns Mon–Sun. Day headers in small uppercase muted text. |
| Calendar navigation | Prev/next month arrows only. Month and year centred between arrows. |
| Day states | Available: dark text, tappable / Selected: filled accent circle, white text / Unavailable: muted grey, not tappable / Other month: greyed out, not tappable / Today: subtle dot beneath the number |
| Time slot layout | Pill buttons in a 3-column grid (`repeat(3, 1fr)`). Pills wrap naturally left-to-right on the last row — no artificial centering of orphaned pills. |
| Time slot grouping | Morning / Afternoon / Evening sections with small uppercase muted label above each group |
| Empty time groups | Hidden entirely — do not show "No slots available" message |
| Slot states | Available: light grey border, white background / Selected: filled accent, white text / Unavailable: muted text and border, light grey background, not tappable |
| Scroll behaviour | Calendar stays visible at top; time slots appear below it on the same screen. No collapse. Single continuous scroll. |
| Evening group | Hidden in reference file (empty). Shown only when evening slots exist. |
| Back link | Active — returns to Step 2 |
| Continue button | Enabled only after both a date and a time slot are selected |

---

## Step 4 — Your details

**Reference file:** `wizard-step4.html`

| Decision | Value |
|---|---|
| Heading | "Your details" |
| Subheading | "Almost there — just a few details to confirm your booking." |
| Confirmation banner | Shows all four previous selections: service · duration · staff · date and time (e.g. "Swedish Massage · 60 min · Elena Torres · Wed 15 Apr, 11:00"). Wraps to two lines on mobile — do not truncate. |
| Field order | First name → Last name → Email → Phone → Special requests (collapsed) → Marketing consent → Cooling-off waiver |
| Field style | Single column, full width, rounded corners (10px), 1.5px border, 12px padding. Label above each field in 13px dark grey. |
| Autocomplete | `given-name` / `family-name` / `email` / `tel` on respective inputs |
| Input modes | `inputmode="email"` on email field · `inputmode="tel"` on phone field |
| Phone placeholder | `07700 900000` (UK domestic mobile format) |
| Special requests | Collapsed by default behind a "+ Add special requests" text link in accent colour. Reveals a textarea on tap. |
| Error state | Red border on input, light red background tint, red error message below the field. Scroll to first errored field on submission attempt. |
| Marketing consent | Optional checkbox, unchecked by default. Label: "Keep me updated with offers and news." Helper text below: "You can unsubscribe at any time." |
| Cooling-off waiver | Shown only when booking is within 14 days. Required checkbox. Visually distinct block: warm amber tint background (`#fffbf0`), amber left border (3.5px, `#e6a817`). Contains bold heading "Important: Right to Cancel", plain-English explanation paragraph, then the required checkbox. |
| Waiver CSS | Amber values are fixed — NOT `--bookit-*` theme tokens. The waiver is a legal signal, not a brand element, and must not be overridden by themes. |
| Back link | Active — returns to Step 3 |
| Continue button | Enabled at all times visually; validation runs on submit |

---

## Step 5 — Payment

**Reference files:**
- `wizard-step5-with-package.html` — customer has an active applicable package
- `wizard-step5-buy-package.html` — customer has no package, upsell available
- `wizard-step5-no-package.html` — no packages in either direction, payment only

### Zone logic

Step 5 has three vertical zones. Zone B is conditional and mutually exclusive with itself — a customer never sees both package sections simultaneously.

| Condition | Zones shown |
|---|---|
| Customer has active applicable package | Zone A + Zone B (use package) + Zone C |
| Customer has no package, packages enabled for service | Zone A + Zone B (buy package) + Zone C |
| Packages disabled or not applicable | Zone A + Zone C only |

### Zone A — Booking summary

| Decision | Value |
|---|---|
| Zone label | "Review your booking" — small uppercase muted text |
| Summary rows | Service · Duration · With · Date · Time — left key, right value, 13px |
| Deposit split | Three rows below a thin divider: "Today (deposit)" / "Remaining (on the day)" / "Total" (bolder, larger). All right-aligned values. |
| No deposit scenario | Single row: "Total due today: £X" |
| Cancellation policy | Collapsed `<details>` disclosure. Label "Cancellation policy" + chevron. One tap to expand. Never shown open by default. |

### Zone B — Packages (conditional, never both variants shown together)

**Use package variant** (customer has active package):

| Decision | Value |
|---|---|
| Zone background | Accent light tint (`--accent-light`) to signal it is a benefit, not a burden |
| Zone label | "Your packages" |
| Intro text | "You have an active package for this service — use a session instead of paying now." |
| Package row | Radio row: package name + sessions remaining + expiry date |
| Selecting package | Greys out Zone C entirely (opacity 0.4, pointer-events none). CTA changes to "Use my package". |
| Deselecting package | Zone C re-enabled. CTA returns to payment method label. |

**Buy package variant** (no active package, upsell available):

| Decision | Value |
|---|---|
| Zone background | Light grey (`#fafaf9`) — neutral, not a benefit signal |
| Zone label | "Save with a package" |
| Intro text | "Book multiple sessions and save — use your first session for this appointment." |
| Package cards | Radio cards showing: bundle name / saving in accent colour / price right-aligned |
| Sub-note | "Your appointment will be confirmed after the package purchase." |
| Selecting a bundle | Greys out Zone C entirely. CTA changes to "Buy package & confirm". |
| Zone C label when buy package shown | "Or pay for this session only" — reinforces the choice |

### Zone C — Payment methods

| Decision | Value |
|---|---|
| Zone label | "How would you like to pay?" (or "Or pay for this session only" when buy package shown) |
| Payment methods | Pay by card (pre-selected) · PayPal · Pay in person |
| Method row style | Full-width radio row, same selected state as all other wizard radio elements |
| Card logos | VISA + MC pill badges, right-aligned in the row |
| PayPal logo | PayPal pill badge, right-aligned |
| Pay in person | No logo. Sub-label: "No payment needed now" |
| Default selection | Pay by card always pre-selected |

### CTA dynamic label

The sticky footer CTA text updates immediately on any selection change — package or payment method.

| Selection | CTA text |
|---|---|
| Pay by card (with deposit) | "Pay £[deposit amount] now" |
| Pay by card (no deposit) | "Pay £[total] now" |
| PayPal | "Continue to PayPal" |
| Pay in person | "Confirm booking" |
| Use existing package | "Use my package" |
| Buy package | "Buy package & confirm" |

### Back link

Active — returns to Step 4.

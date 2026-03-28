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

## Steps 2–5

_To be added as each step is designed._

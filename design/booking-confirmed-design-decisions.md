# Bookit — Booking Confirmed Page: Design Decisions

This document records agreed design decisions for the booking confirmation
page shown after a customer completes a booking. These decisions drive the
Cursor implementation prompt.

The HTML reference file lives alongside this document in `/design/`.

---

## Reference file

`design/booking-confirmed.html`

---

## Page purpose and context

Shown immediately after a successful booking (Stripe payment, PayPal
payment, or Pay on Arrival). The customer has just completed a multi-step
flow and needs immediate reassurance that their booking is confirmed,
plus the key details they need to remember or refer back to.

Emails are sent asynchronously via the Action Scheduler queue — the page
must not imply the email has already arrived.

---

## Layout and structure

| Decision | Value |
|---|---|
| Max-width | 680px, centred |
| Layout | Single column, mobile-first |
| Background | `--bookit-bg-page` (light grey) |
| Gap between sections | 12px |
| Font | Inherits `--bookit-font-family` |

---

## Section 1 — Success header

| Decision | Value |
|---|---|
| Icon | Filled circle (`--bookit-primary` background), white SVG checkmark inside. Circle 64px. SVG stroke, not text character. |
| Heading | "Booking confirmed" — 24px, font-weight 700, `--bookit-text-primary` |
| Sub-copy | "We'll send a confirmation to **[email]** shortly." — 14px, `--bookit-text-secondary`. Email address in `--bookit-text-primary` bold. |
| Email copy rule | Must say "shortly" — NOT "has been sent". Emails are async queue. |
| Container | White card, `--bookit-border`, `--bookit-border-radius`, centred content, 2rem top padding |

---

## Section 2 — Booking detail card

| Decision | Value |
|---|---|
| Container | White card, `--bookit-border`, `--bookit-border-radius` |
| Layout | Side-by-side rows: label left (`--bookit-text-secondary`, 13px), value right (bold, `--bookit-text-primary`, 13px) |
| Row separators | Thin `1px solid --bookit-border` between rows, none on last row |
| Rows | Service / Duration / With / Date / Time / Booking ref |
| Date format | Full UK format: "Wednesday, 15 April 2026" |
| Time format | 24-hour: "11:00" |
| Booking ref | Human-readable reference field (e.g. `BK-20260415-0042`) — rendered in `--bookit-primary` colour, font-weight 500, slightly smaller (12px), letter-spacing 0.02em. Must NOT use the raw database ID. |
| Mobile stacked | `@media (max-width: 380px)` — label above value, both left-aligned |
| Long values | `word-break: break-word` on value cell prevents overflow |

---

## Section 3 — Payment card (conditional)

Shown when a deposit was taken or a full payment was made online.
Not shown for Pay on Arrival (see Section 3b).

| Decision | Value |
|---|---|
| Container | Separate white card from the detail card |
| Rows | "Today (deposit)" / "Remaining (on the day)" / "Total" |
| Total row | Font-weight 700, slightly larger (15px) on both key and value |
| Row separators | `1px solid --bookit-border` between rows |
| Balance note | Inset grey box (`--bookit-bg-page`, `border-radius: 8px`, 10px 12px padding) below the rows. Copy: "Your remaining balance is payable on the day of your appointment." 12px, `--bookit-text-secondary`. |
| No deposit scenario | Single row only: "Total paid today" / £X. No balance note. |

### Section 3b — Pay on Arrival block (conditional)

Shown when `payment_method = 'pay_on_arrival'`. Replaces Section 3.

| Decision | Value |
|---|---|
| Container | Same card style as Section 3 |
| Copy | "No deposit was taken. Please bring £[total] to your appointment." |
| Style | 13px, `--bookit-text-secondary`, no rows — plain paragraph |

---

## Section 4 — Cancellation note

| Decision | Value |
|---|---|
| Container | White card, `--bookit-border`, `--bookit-border-radius`, 12px 1.5rem padding |
| Copy | "To cancel or reschedule, use the link in your confirmation email." |
| Style | 13px, `--bookit-text-secondary`, centred |
| Business phone | If `business_phone` is set in settings, add a second line: "Or call us on [phone]." |

---

## Section 5 — Conditional blocks

These appear between the cancellation note and the actions, in this order,
when the relevant conditions are met.

### Cooling-off waiver acknowledgement (conditional)

Shown when `cooling_off_waiver_given = true`.

| Decision | Value |
|---|---|
| Container | Amber tint block — same `--bookit-v2-waiver-*` token values as wizard Step 4. Fixed amber, not `--bookit-primary`. |
| Copy | "✓ You have waived your 14-day right to cancel for this booking (Consumer Contracts Regulations 2013)." |
| Style | 13px |

### Special requests (conditional)

Shown when `special_requests` is non-empty.

| Decision | Value |
|---|---|
| Container | Light grey card (`--bookit-bg-page`) |
| Label | "Your special requests" — 13px, `--bookit-text-secondary`, uppercase |
| Value | Customer's special requests text, 13px, `--bookit-text-primary` |

### Meeting link (extension hook)

Output of `bookit_confirmation_meeting_section` filter.
Rendered if non-empty, no wrapper added by the core template.

---

## Section 6 — Actions

| Decision | Value |
|---|---|
| Layout | Stacked vertically, full width, gap 10px |
| Primary CTA | Filled button, `--bookit-btn-primary-bg`, white text, `--bookit-btn-radius`. Label: "Add to calendar". Small inline SVG calendar icon left of text. |
| Secondary CTA | Ghost button, accent colour border and text, `--bookit-btn-radius`. Label: "Back to home". |
| Tertiary | Text link only, `--bookit-text-muted`, centred. Label: "Book again". |
| Add to calendar | Generates and downloads a `.ics` file for the booking |

---

## Bug fixes required (Sprint 5)

These are code issues identified during the UX review, not design decisions.

| Issue | Detail |
|---|---|
| Stale email-sending code | `booking-confirmed.php` still calls `Booking_System_Email_Sender` directly. Since Sprint 4H emails route through the queue. Audit whether this causes double-queuing and remove the direct call if so. |
| Email copy | Current copy says "A confirmation email has been sent" — must change to "We'll send a confirmation shortly" |
| Booking reference | Page currently shows raw `$booking['id']` — must use `$booking['booking_reference']` (human-readable ref field) |
| Styled checkmark | Current template uses plain text ✓ — replace with CSS circle + SVG stroke checkmark |
| Mobile layout | Current CSS uses `flex: 0 0 40%/60%` which can overflow on narrow viewports — replace with the side-by-side/stacked pattern from the reference file |

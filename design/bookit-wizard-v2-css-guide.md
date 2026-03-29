# Bookit Wizard V2 — CSS Customisation Guide

This guide explains how to customise the appearance of the Bookit Wizard V2 booking flow without modifying plugin files.

---

## How overrides work

The Bookit plugin loads its stylesheet **after** your theme stylesheet. This means simple `:root` variable declarations in your theme will be overridden by the plugin's own defaults unless you use `!important`.

Add your overrides to your theme's `style.css` or a Custom CSS block in the WordPress Customiser:

```css
:root {
    --bookit-primary:       #E91E63 !important;
    --bookit-border-radius: 4px     !important;
}
```

**The `!important` flag is required on every override.** Without it, the plugin's defaults will win.

---

## Template overrides

For deeper structural changes, you can override any PHP template by copying it from the plugin to your theme:

- Plugin location: `wp-content/plugins/bookit-booking-system/public/templates/`
- Theme override location: `wp-content/themes/{your-theme}/bookit/`

Child themes are supported — Bookit checks child theme first, then parent theme, then the plugin default.

> **Note:** When the plugin updates, check your overridden templates for changes. Outdated overrides may break if the template's expected variables change.

---

## Available CSS tokens

### Brand colours

These are the most commonly changed tokens. Updating `--bookit-primary` alone will change the accent colour across all five wizard steps simultaneously.

| Token | Default | What it controls |
|---|---|---|
| `--bookit-primary` | `#4F46E5` | Accent colour — buttons, selected states, progress bar, focus rings, slot highlights |
| `--bookit-primary-hover` | `#4338CA` | CTA button hover state |
| `--bookit-primary-light` | `#EEF2FF` | Selected card backgrounds, Zone B use-package tint |
| `--bookit-accent` | `#10B981` | Completed step indicator in the progress bar |

### Text colours

| Token | Default | What it controls |
|---|---|---|
| `--bookit-text-primary` | `#111827` | Headings, service names, staff names, labels, values |
| `--bookit-text-secondary` | `#6B7280` | Subheadings, helper text, summary keys, banner text |
| `--bookit-text-muted` | `#9CA3AF` | Zone labels, placeholder text, disabled states |
| `--bookit-text-inverse` | `#FFFFFF` | Text on filled primary buttons |

### Backgrounds

| Token | Default | What it controls |
|---|---|---|
| `--bookit-bg-card` | `#FFFFFF` | Service cards, staff cards, payment rows |
| `--bookit-bg-input` | `#FFFFFF` | Contact form input fields |
| `--bookit-bg-page` | `#F9FAFB` | Page background |

### Borders and shape

| Token | Default | What it controls |
|---|---|---|
| `--bookit-border` | `#E5E7EB` | All card, row, and divider borders |
| `--bookit-border-focus` | `#4F46E5` | Input focus ring colour |
| `--bookit-border-radius` | `8px` | Corner radius on cards, inputs, and payment rows |
| `--bookit-border-radius-sm` | `4px` | Corner radius on smaller elements |

### Typography

| Token | Default | What it controls |
|---|---|---|
| `--bookit-font-family` | System UI stack | All wizard text |
| `--bookit-font-size-sm` | `0.875rem` | Small text |
| `--bookit-font-size-base` | `1rem` | Body text |
| `--bookit-font-size-lg` | `1.125rem` | Large text |
| `--bookit-line-height` | `1.5` | Line spacing |

### Buttons

| Token | Default | What it controls |
|---|---|---|
| `--bookit-btn-primary-bg` | `var(--bookit-primary)` | CTA button background (defaults to primary colour) |
| `--bookit-btn-primary-text` | `#FFFFFF` | CTA button text colour |
| `--bookit-btn-radius` | `var(--bookit-border-radius)` | CTA button corner radius |

### Status colours

| Token | Default | What it controls |
|---|---|---|
| `--bookit-color-error` | `#EF4444` | Validation error messages and input error borders |
| `--bookit-color-success` | `#10B981` | Success states |
| `--bookit-color-warning` | `#F59E0B` | Warning states |
| `--bookit-color-info` | `#3B82F6` | Info states |

### V2 layout tokens

These control structural dimensions specific to the V2 wizard.

| Token | Default | What it controls |
|---|---|---|
| `--bookit-v2-max-width` | `680px` | Maximum width of the wizard container |
| `--bookit-v2-progress-height` | `2.5px` | Thickness of the progress bar underlines |
| `--bookit-v2-avatar-size-list` | `36px` | Staff avatar size in list layout (1–3 staff) |
| `--bookit-v2-avatar-size-grid` | `44px` | Staff avatar size in grid layout (4+ staff) |
| `--bookit-v2-slot-radius` | `10px` | Corner radius on time slot buttons |
| `--bookit-v2-banner-bg` | `#f7f6f4` | Confirmation banner background colour |
| `--bookit-v2-zone-label-size` | `10px` | Font size for zone labels (e.g. "Review your booking") |

---

## What cannot be overridden

The **cooling-off waiver block** (the amber legal notice on step 4) uses fixed colour values that are intentionally not overridable. These must remain visually distinct as they carry a legal meaning under the Consumer Contracts Regulations 2013.

```css
/* These values are fixed — do not override */
--bookit-v2-waiver-bg:      #fffbf0;   /* Amber tint background */
--bookit-v2-waiver-border:  #e6a817;   /* Amber left border */
--bookit-v2-waiver-heading: #92610a;   /* Dark amber heading */
--bookit-v2-waiver-text:    #5c3d06;   /* Dark amber body text */
```

---

## Examples

### Pink salon theme

```css
:root {
    --bookit-primary:          #D4467A !important;
    --bookit-primary-hover:    #B83A69 !important;
    --bookit-primary-light:    #FDF0F5 !important;
    --bookit-border-radius:    12px    !important;
    --bookit-font-family:      'Lato', sans-serif !important;
}
```

### Minimal dark-border theme

```css
:root {
    --bookit-primary:          #111827 !important;
    --bookit-primary-hover:    #374151 !important;
    --bookit-primary-light:    #F3F4F6 !important;
    --bookit-border:           #111827 !important;
    --bookit-border-radius:    4px     !important;
}
```

### Wider wizard for desktop-first sites

```css
:root {
    --bookit-v2-max-width: 860px !important;
}
```

### Custom font

```css
:root {
    --bookit-font-family: 'Playfair Display', Georgia, serif !important;
}
```

> Remember to load your custom font via `@import` or `<link>` before applying it as a token.

---

## Quick reference — most common overrides

For most client implementations, only the following four tokens need to change:

```css
:root {
    --bookit-primary:       /* your brand colour */       !important;
    --bookit-primary-hover: /* your brand colour, 10% darker */ !important;
    --bookit-primary-light: /* your brand colour, 5% opacity */ !important;
    --bookit-font-family:   /* your theme font */          !important;
}
```

Everything else will inherit from these four values automatically.

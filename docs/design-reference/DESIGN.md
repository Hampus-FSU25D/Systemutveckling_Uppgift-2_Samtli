---
name: Samtli
colors:
  surface: '#faf9f7'
  surface-dim: '#dadad8'
  surface-bright: '#faf9f7'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f4f3f1'
  surface-container: '#efeeec'
  surface-container-high: '#e9e8e6'
  surface-container-highest: '#e3e2e0'
  on-surface: '#1a1c1b'
  on-surface-variant: '#444748'
  inverse-surface: '#2f3130'
  inverse-on-surface: '#f1f1ef'
  outline: '#747878'
  outline-variant: '#c4c7c7'
  surface-tint: '#5f5e5e'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#1c1b1b'
  on-primary-container: '#858383'
  inverse-primary: '#c8c6c5'
  secondary: '#3f5d9c'
  on-secondary: '#ffffff'
  secondary-container: '#9bb8fe'
  on-secondary-container: '#284785'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#1b1c1c'
  on-tertiary-container: '#848484'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#e5e2e1'
  primary-fixed-dim: '#c8c6c5'
  on-primary-fixed: '#1c1b1b'
  on-primary-fixed-variant: '#474746'
  secondary-fixed: '#d9e2ff'
  secondary-fixed-dim: '#afc6ff'
  on-secondary-fixed: '#001944'
  on-secondary-fixed-variant: '#254583'
  tertiary-fixed: '#e3e2e2'
  tertiary-fixed-dim: '#c7c6c6'
  on-tertiary-fixed: '#1b1c1c'
  on-tertiary-fixed-variant: '#464747'
  background: '#faf9f7'
  on-background: '#1a1c1b'
  surface-variant: '#e3e2e0'
typography:
  headline-xl:
    fontFamily: Manrope
    fontSize: 40px
    fontWeight: '700'
    lineHeight: 48px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Manrope
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Manrope
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-sm:
    fontFamily: Manrope
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Manrope
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Manrope
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-sm:
    fontFamily: Manrope
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-md:
    fontFamily: Manrope
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Manrope
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.02em
  headline-xl-mobile:
    fontFamily: Manrope
    fontSize: 30px
    fontWeight: '700'
    lineHeight: 36px
  headline-lg-mobile:
    fontFamily: Manrope
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  unit: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 40px
  gutter: 24px
  margin: 32px
  container-max: 1120px
---

## Brand & Style

This design system embodies Scandinavian functionalism: a marriage of warmth, utility, and restraint. The personality is communal yet disciplined, prioritizing legibility and the quiet exchange of ideas over visual noise.

The aesthetic is **Refined Minimalism**. It avoids the sterility of pure white by utilizing a tactile, warm paper-like base. Visual hierarchy is established through precise typography and spatial relationships rather than decorative effects. The emotional goal is to provide a "quiet room" for public discourseâ€”grounded, trustworthy, and human-centric.

## Colors

The palette is anchored by a warm off-white surface that reduces eye strain and provides a soft, organic foundation. Text uses a near-black for high contrast without the harshness of pure black.

- **Surface:** The primary canvas is `#F9F8F6`.
- **Primary Text:** `#1A1A1A` for all headers and body copy.
- **Secondary Text:** `#737373` for metadata, timestamps, and captions.
- **Accents:** A muted cobalt blue is used sparingly for interactive triggers and primary actions.
- **Borders:** A subtle warm-gray creates low-contrast containment for UI elements.
- **Feedback:** Semantic green and red are muted to stay consistent with the restrained environment.

## Typography

The design system utilizes **Manrope** for its modern, geometric balance and open counters, which maintain legibility even in dense forum threads.

Typography is the primary driver of the UI. Large headers use a slight negative letter-spacing to appear more cohesive, while labels use positive letter-spacing for clarity at small scales. Content density should be managed through generous line heights, ensuring that long-form discussions remain approachable.

## Layout & Spacing

The layout follows a **Fixed Grid** model on desktop, centering content within a 1120px container to keep line lengths readable for forum posts.

A 4px baseline grid governs all vertical rhythm. Spacing is intentionally generous to create a sense of "Air"â€”a core Scandinavian principle.
- **Desktop:** 12-column grid with 24px gutters.
- **Tablet:** 8-column grid with 20px gutters.
- **Mobile:** 4-column grid with 16px margins; content flows vertically in a single column.

Avoid tightly packed "dashboard" layouts. Use whitespace to separate discussion topics rather than heavy divider lines where possible.

## Elevation & Depth

This design system rejects deep shadows and skeuomorphism in favor of **Tonal Layers** and **Low-Contrast Outlines**.

- **Level 0 (Surface):** The background (#F9F8F6).
- **Level 1 (Cards/Inputs):** Defined by a 1px solid border (#E5E1DC). No shadow.
- **Level 2 (Popovers/Dropdowns):** Uses a subtle, high-dispersion shadow: `0 4px 12px rgba(26, 26, 26, 0.05)`.

Depth is primarily suggested by stacking order and slight shifts in background tint (using white `#FFFFFF` for elevated cards against the off-white background).

## Shapes

The shape language is "Soft-Square." It uses a modest corner radius to appear friendly but retains a structured, architectural feel.

- **Small elements (Buttons, Inputs):** 4px radius.
- **Medium elements (Cards, Modals):** 6px radius.
- **Large elements (Containers):** 8px radius.

Avatars should be square with a 4px radius, using initials and a specific tonal color-fill to maintain the minimal, non-photographic focus of the forum.

## Components

- **Buttons:** Primary buttons use the Cobalt Blue (#3B5998) with white text. Secondary buttons are outlined with `#E5E1DC` and use Primary Text. No gradients.
- **Inputs:** Fields use a white background, 1px border, and 4px radius. Focus states are indicated by a 1px solid Primary Color borderâ€”no outer glow.
- **Chips:** Used for forum tags. Light gray background (#F0EDE9) with secondary text. No border.
- **Cards:** White (#FFFFFF) background with a 1px border (#E5E1DC). Avoid shadows on static cards; reserved for hover states if necessary.
- **Lists:** Thread lists should use generous padding (16px - 24px) between items, separated by a single 1px hairline divider.
- **Icons:** Use 20px or 24px stroke-based icons with a consistent 1.5px weight. Never filled.
- **Avatars:** Square with 4px radius. Initials centered. Backgrounds for avatars should be pulled from a secondary palette of muted earth tones (sage, terracotta, slate).
# Samtli Design Reference

This directory contains the visual reference migrated from the supplied Google Stitch export.

## Authority

- Screenshots are the visual source of truth where available.
- `DESIGN.md` contains shared design tokens and visual guidance.
- Generated `code.html` files are reference material only.
- Production PHP and CSS must be implemented independently and cleanly.
- Assignment requirements override any functionality invented by Stitch.
- Frontend frameworks, Tailwind utility markup or generated scripts from Stitch must not be copied into production runtime.
- Shared components must be consolidated into canonical reusable application components.
- Future design changes must consult this reference first.

Do not create a visually different header, button, form control, group card or other shared component merely because an individual Stitch screen contains a slight generated variation. Inspect all relevant references and maintain one coherent Samtli design system.

## Files

- `DESIGN.md`: exported tokens and design direction.
- `SCREEN_INVENTORY.md`: list of discovered screens and their implementation relevance.
- `COMPONENT_INVENTORY.md`: canonical decisions for shared UI components.
- `stitch-export/`: preserved generated screen directories with `code.html` and `screen.png`.

# Pluribus design system

Unified UI guidance blending **Swiss Design** (grid, typographic hierarchy,
objective layout), **Material Design** (8dp spacing, elevation, motion,
accessibility), and **Metro** (content over chrome, flat segments, bold
headings). Builds on the utilitarian, mobile-first product stance in the main
README.

Cursor rules: `.cursor/rules/frontend-design-system.mdc`  
Token source: `frontend/src/style.scss`

## Priority when principles conflict

| Goal | Primary influence |
|------|-------------------|
| Hierarchy and whitespace | Swiss + Metro |
| Spacing rhythm and focus states | Material |
| Flat app chrome | Metro |
| Subtle depth on cards/modals only | Material (max 2 levels) |

## Spacing (8dp grid)

| Token | Value |
|-------|-------|
| `--space-1` | 4px |
| `--space-2` | 8px |
| `--space-3` | 12px |
| `--space-4` | 16px |
| `--space-5` | 24px |
| `--space-6` | 32px |
| `--space-7` | 48px |
| `--space-8` | 64px |

## Typography

| Token | Typical use |
|-------|-------------|
| `--text-xs` | Captions, hints |
| `--text-sm` | Labels, secondary |
| `--text-md` | Body |
| `--text-lg` | Subheadings |
| `--text-xl` | Section titles |
| `--text-2xl` | Page titles (`Title` h1) |

Use the `Title` atom with `tag="h1"` … `h4` for heading levels.

## Shape and elevation

| Token | Value / use |
|-------|-------------|
| `--radius-sm` | 2px — chips, tight controls |
| `--radius-md` | 4px — inputs, buttons |
| `--radius-lg` | 8px — cards |
| `--shadow-1` | Resting card |
| `--shadow-2` | Raised panel / modal |

## Semantic colors

Use `--color-primary`, `--color-on-primary`, `--color-surface`,
`--color-on-surface`, `--color-outline`, `--color-danger`, and
`--color-focus-ring`. Legacy aliases (`--accent`, `--bg`, `--link`) remain for
compatibility.

## Motion

- `--motion-fast`: 120ms — hover, opacity
- `--motion-standard`: 180ms — background, border
- Respect `prefers-reduced-motion` (global shorten/disable in `style.scss`)

## Component recipes

### Button

- Variants: `primary`, `secondary`, `ghost`, `danger`, `link`
- Sizes: `sm`, `md`, `lg`
- Must use tokens; include `:focus-visible` ring

### Card

- Background `var(--color-surface)`, padding `var(--space-6)`, radius
  `var(--radius-lg)`, border `var(--color-outline)`

### Input

- Label: `--text-sm`, `--font-weight-semibold`
- Field border: `--color-outline`; focus: `--color-focus-ring`

## Atomic design

- `atoms/` — primitives (`Button`, `Input`, `Title`, `Card`)
- `molecules/` — simple groups
- `organisms/` — sections
- `views/` — route-level pages and data wiring

Do not import upward (e.g. atoms must not import organisms).

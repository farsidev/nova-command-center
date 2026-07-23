---
name: Nova Command Center
description: Security-first allow-listed command runner for Laravel Nova — host-native ops UI.
colors:
  primary: "#4099de"
  danger: "#e74c3c"
  warning: "#d99a00"
  success: "#22c55e"
  success-text: "#16a34a"
  success-dark: "#4ade80"
  failed: "#ef4444"
  running: "#3b82f6"
  running-dark: "#60a5fa"
  pending: "#94a3b8"
  timed-out: "#f59e0b"
  artisan: "#2563eb"
  artisan-dark: "#93c5fd"
  bash: "#b45309"
  surface: "#ffffff"
  surface-muted: "#f8fafc"
  surface-dark: "#1e293b"
  surface-dark-elevated: "#263449"
  canvas-console: "#0f172a"
  text-heading: "#0f172a"
  text-strong: "#1e293b"
  text-body: "#334155"
  text-muted: "#475569"
  text-faint: "#64748b"
  border: "#e9edf2"
  border-dark: "#313d4f"
  traffic-red: "#ff5f57"
  traffic-yellow: "#febc2e"
  traffic-green: "#28c840"
  shadow-dark: "rgba(0, 0, 0, 0.25)"
  shadow-dark-deep: "rgba(0, 0, 0, 0.55)"
  focus-dark: "rgba(96, 165, 250, 0.6)"
typography:
  title:
    fontFamily: "inherit"
    fontSize: "1.5rem"
    fontWeight: 400
    lineHeight: 1.25
  body:
    fontFamily: "inherit"
    fontSize: "0.875rem"
    fontWeight: 500
    lineHeight: 1.4
  label:
    fontFamily: "inherit"
    fontSize: "0.875rem"
    fontWeight: 700
    lineHeight: 1.3
  button:
    fontFamily: "inherit"
    fontSize: "0.8rem"
    fontWeight: 700
    lineHeight: 1
  mono:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace"
    fontSize: "0.75rem"
    fontWeight: 400
    lineHeight: 1.6
  section:
    fontFamily: "inherit"
    fontSize: "0.7rem"
    fontWeight: 700
    letterSpacing: "0.06em"
    lineHeight: 1.2
  badge:
    fontFamily: "inherit"
    fontSize: "0.65rem"
    fontWeight: 700
    letterSpacing: "0.03em"
    lineHeight: 1.2
rounded:
  xs: "0.25rem"
  sm: "0.375rem"
  md: "0.5rem"
  md-plus: "0.55rem"
  search: "0.6rem"
  icon: "0.45rem"
  badge-icon: "0.7rem"
  lg: "0.75rem"
  pill: "9999px"
spacing:
  xs: "0.25rem"
  sm: "0.5rem"
  md: "0.75rem"
  lg: "1rem"
  xl: "1.5rem"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "#ffffff"
    rounded: "{rounded.md}"
    padding: "0.55rem 1rem"
    height: "2.75rem"
  button-danger:
    backgroundColor: "{colors.danger}"
    textColor: "#ffffff"
    rounded: "{rounded.md}"
    padding: "0.55rem 1rem"
    height: "2.75rem"
  button-warning:
    backgroundColor: "{colors.warning}"
    textColor: "#ffffff"
    rounded: "{rounded.md}"
    padding: "0.55rem 1rem"
    height: "2.75rem"
  card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.lg}"
    padding: "0.8rem 1rem"
  console:
    backgroundColor: "{colors.canvas-console}"
    textColor: "#e2e8f0"
    rounded: "{rounded.lg}"
    typography: "{typography.mono}"
---

# Design System: Nova Command Center

## Overview

**Creative North Star: "The Host-Native Console"**

Nova Command Center is an Operate-mode tool that lives inside Laravel Nova. Visual authority comes from matching the host admin — Nova’s light/dark switch, primary accent, density — while package-owned CSS keeps every surface readable when the host’s purged Tailwind build omits utilities. Craft shows up in precise details (focus rings, confirm-safe modal focus, status accents that mean something) rather than a separate product brand shell.

Qualitative language below is derived from confirmed PRODUCT.md commitments (Nova-coherent, security-honest, operator clarity) and the incumbent `resources/css/tool.css` system — not a greenfield visual world.

**Key Characteristics:**
- Host-native: inherit Nova `--primary`; follow `.dark` on `<html>`
- Meaning-first color: only danger/warning diverge from primary on Run controls
- Purge-safe: colors, icons, fractional sizes live in package CSS, not host utilities
- Dense admin layout with 44px minimum touch targets on interactive chrome

## Colors

Slate neutrals for structure; Nova primary for action; red/amber reserved for risk.

### Primary
- **Nova Primary** (`#4099de` / `var(--primary)`): Run buttons (safe types), focus rings, active category, progress fill, icon badge tint.

### Semantic
- **Risk Red** (`#e74c3c`): Danger commands, confirm notices, failed status.
- **Caution Amber** (`#d99a00` / `#f59e0b`): Warning commands, timed-out status.
- **Success Green** (`#22c55e` / `#16a34a`): Successful runs.
- **Running Blue** (`#3b82f6`): In-flight / queued activity.
- **Artisan Blue** / **Bash Amber**: Type badges only.

### Neutral
- **Surface** (`#ffffff` / dark `#1e293b`): Cards, modal, progress strip.
- **Console Canvas** (`#0f172a`): Always-dark terminal body (intentional, both themes).
- **Text Heading → Faint** (`#0f172a` → `#64748b`): Hierarchy steps; muted/faint meet AA on white.
- **Border** (`#e9edf2` / dark `#313d4f`): Cards, rows, inputs.

### Named Rules
**The Meaning Accent Rule.** Only `danger` and `warning` command types get non-primary Run/stripe colors. Other types share Nova primary — decorative rainbow accents are noise.

**The Purge-Safe Rule.** Never rely on host Tailwind color utilities for tool chrome. Put colors in `tool.css` (with `.dark` twins).

## Typography

**Display/Body Font:** Inherit Nova’s UI stack (do not introduce a marketing typeface).  
**Mono Font:** `ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace` for run strings, console, timers, custom-command input.

**Character:** Operational and quiet — weight and truncation create hierarchy, not display type.

### Hierarchy
- **Title** (normal, ~1.5rem): Page heading (`.ncr-heading`).
- **Body strong** (bold, ~0.875–1rem): Command names, modal titles.
- **Label** (bold, 0.875rem): Form labels (`.ncr-label`).
- **Section** (700, 0.7rem, tracked uppercase): Group headers.
- **Mono** (0.75rem, 1.6): Console output and run previews.

## Layout

- **Breakpoints:** Stacked columns by default; from `1024px`, three-column row (rail 15rem | main | history 20rem) with sticky side columns.
- **Mobile rail:** Horizontal scroll category chips below search (`ncr-rail-mobile`).
- **Custom bar:** Flex-wrap; full-stack under `25rem`.
- **Density:** Admin-compact; interactive controls use **min 2.75rem (44px)** height/hit area.
- **Truncation:** `.ncr-truncate` / `.ncr-shrink` — package-owned `min-width: 0`, not host `min-w-0`.

## Elevation & Depth

Light, structural shadows — not decorative glow.

- Card rest: soft 1–3px slate shadow.
- Card hover (interactive): deeper lift + `translateY(-2px)`; disabled under `prefers-reduced-motion`.
- Modal: backdrop `rgba(15,23,42,0.55)` + light blur; modal pops with short scale/opacity.
- Model-search list: fixed-position elevated panel (escapes modal overflow).

## Shapes

- Cards / console / modal: `0.75rem`
- Buttons / flags / inputs: `0.5rem`
- Badges / progress / dots: pill `9999px`
- Accent stripe: 5px left bar via `scaleX` (not layout `width` animation)

## Components

### Command card (`.ncr-cmd`)
Left accent stripe (type-meaningful), artisan/bash badge, truncated name/run/help, primary Run button. Interactive hover lift.

### Run modal
Dialog with focus trap; focus first field or Cancel (never Run) for confirm-only. Risk notice for danger/warning. Variables + flags; invalid fields use `.ncr-field-invalid` + `role="alert"` errors.

### Output console
Mac-traffic decorative chrome (`aria-hidden`), status text + dots, copy control, optional progressbar, always-dark `<pre>` with live cursor while running.

### History
Keyboard-activatable rows (`role="button"`); status stripe; rerun always visible on coarse pointers, quieter on fine hover. Clear is two-step arm.

### Category rail
Buttons with `aria-current`; curated category palette (not full hue wheel) via `categoryColor()`.

### Model search
Combobox + listbox: `aria-controls`, `aria-activedescendant`, option roles; fixed positioning for overflow escape.

## Do's and Don'ts

### Do
- Follow Nova `.dark` and `--primary`
- Keep danger/warning as the only semantic Run accents
- Prefer package CSS for colors, icons, and fractional sizes
- Preserve keyboard paths (modal trap, history keys, combobox arrows)
- Respect `prefers-reduced-motion`

### Don't
- Invent a marketing brand UI that fights Nova chrome
- Rainbow-color every command type
- Hide critical actions behind hover-only on touch
- Depend on host Tailwind purge for contrast or icon size
- Soft-pedal risk copy in confirmations or notices

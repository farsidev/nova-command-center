# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Primary users are trusted Laravel Nova administrators and operators who need to run pre-approved Artisan (and occasionally shell) commands from the admin dashboard — clear cache, forget a key, migrate, deploy helpers — without leaving Nova or opening SSH.

Secondary audience: Laravel / Nova developers who install and configure the package (allow-list, gates, queue, searchable models) for those operators.

## Product Purpose

Nova Command Center lets operators run an allow-listed set of commands safely from Laravel Nova. Success means a trusted admin can complete routine ops tasks from the panel with clear confirmation, live output, and history — without shell injection risk and without a separate ops console.

## Positioning

Security-first command execution for Nova: user input never becomes shell string interpolation; only configured commands run; bash and ad-hoc custom commands are off by default. Built as a clean-room Nova 4 & 5–compatible tool that fixes the failure modes of earlier command-center packages (injection, Nova 5 `__` crash, weak auth, brittle optional variables).

## Operating Context

- Lives as a Nova tool page inside a host Laravel application’s Nova panel (light/dark theme from the host).
- Commands are defined in version-controlled config (recommended) or optionally managed via a database source + Nova resource.
- Runs sync or queued with polled output and optional progress reporting from child Artisan commands.
- Authorization stacks tool `canSee`, a global gate, and optional per-command `can` abilities (gated commands are hidden from the catalogue).
- History is cache-backed (no required migration) and supports re-run with restored inputs.

## Capabilities and Constraints

**Capabilities (confirmed):** allow-listed Artisan/bash runs; variables (`text`, `select`, `model`) and flags; confirmation for risky types; sync & queued execution; live/polled output; progress bars; history + replay; concurrency locks; rate limiting; doctor CLI (`nova-command-center:check`); pluggable command sources.

**Constraints (binding):**
- Must remain a Nova tool — operates inside Nova’s admin chrome; does not ship a separate standalone product UI or competing brand shell.
- Visual craft inside that shell may improve freely (hierarchy, spacing, motion, empty states, clarity of risk, etc.) as Nova-coherent refinement.
- Security model is non-negotiable: argv / Symfony Process, allow-list only, bash and custom commands off by default, validated inputs, authorization required.
- Targets PHP 8.1+, Laravel 10–12, Nova 4.x and 5.x; frontend built with Laravel Mix and committed `dist/` assets.

**Undecided:** no separate product accessibility standard beyond matching Nova and sensible WCAG practice for the tool UI; no formal SLA or paid support tier recorded.

## Brand Commitments

- Product name: **Nova Command Center** (Composer: `farsi/nova-command-center`).
- Publisher: Farsidev.
- Voice in docs and UI: direct, operational, security-honest — state risks (especially database command source) plainly.
- Identity constraint: Nova-coherent; do not invent a marketing brand system that fights the host admin.

## Evidence on Hand

- Screenshots and demo: `docs/screenshots/` (command center, model search, command editor, demo.gif).
- Docs: `README.md`, `docs/` (configuration, security, authorization, sources, frontend, progress).
- Security policy: `SECURITY.md`.
- Public package: Packagist `farsi/nova-command-center`; GitHub `farsidev/nova-command-center`.

Do not fabricate testimonials, install counts, customer names, or performance benchmarks.

## Product Principles

1. **Safety before convenience** — defaults deny shell and ad-hoc runs; the allow-list is sensitive configuration.
2. **Operator clarity** — confirm risk, show live outcome, preserve history and inputs for re-run.
3. **Host-native craft** — feel at home in Nova; raise quality without leaving the admin language.
4. **Honest docs** — document threat model and dangerous options (database source, bash) without soft-pedaling.
5. **One trust boundary** — every source and UI path still goes through the same coercion, validation, and execution model.

## Accessibility & Inclusion

Match Nova’s admin accessibility expectations and keep the tool keyboard-operable (focus, dialogs, history). No stricter product-specific standard was established; do not invent one.

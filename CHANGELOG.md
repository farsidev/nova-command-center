# Changelog

All notable changes to `farsi/nova-command-center` will be documented in this
file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-07-23

### Added

- `php artisan nova-command-center:check` — a doctor command that statically
  validates the allow-list and configuration and reports everything that
  otherwise fails silently at runtime: commands dropped for a missing `run`
  string, `{placeholders}` with no matching variable (passed literally to the
  process), required selects that can never be satisfied, model variables
  whose class is missing or not allow-listed, bash commands while bash is
  disabled, `can` abilities no gate defines, overlap locks on a cache store
  that can't lock, and a database source whose migration hasn't run. Exits
  non-zero on errors (CI-friendly); `--strict` fails on warnings too.
- Flags now support a `help` line, shown under the flag's label in the run
  modal — bringing them in line with variables, which already had it. The
  bundled Command resource's structured editor gained a matching "Help" field.
- A `model` variable's `default` now resolves to a friendly label the moment
  the run modal opens, instead of showing the raw stored value in the search
  box. The search endpoint accepts a new `?value=` mode for this (resolve one
  known value), alongside its existing free-text `?q=` search.
- History now stores the resolved variables and enabled flags for each run.
  Re-run opens the modal prefilled with those inputs (or re-executes
  immediately for input-less commands).
- Database command source parity for `confirm` — nullable column, model field,
  and a three-option select on the Nova resource (default / always / never),
  matching the config key. An additive migration is published for existing
  installs.

### Changed

- The command catalogue is filtered by per-command `can` abilities. Operators
  no longer see buttons that would only 403 on execute — the same Gate check
  used at run time.
- The visual language is calmer and more deliberate. Category dots/section
  markers now draw from a small curated palette instead of hashing the name
  to an arbitrary point on the entire colour wheel, and Run buttons/accent
  stripes only differ in colour when the difference means something: danger
  and warning commands (the two types that force a confirmation) keep their
  red/amber, while every other type shares Nova's own primary colour instead
  of each getting its own decorative hue. The header icon badge dropped its
  gradient for a flat tint.
- Full keyboard support: history rows are now reachable and activatable with
  the keyboard (they were click-only), every interactive element has a
  visible focus ring, the run modal traps Tab within itself, labels the
  dialog for screen readers, and focuses the first field on open — or the
  Cancel button (never Run) for confirm-only commands, so a stray Enter
  can't fire the command the confirmation exists to guard.
- The model-search results list is no longer clipped by the run modal's
  scrolling body; it now escapes any scroll container and flips above the
  input when there's no room below.
- The three independent places that parsed the "variables/flags can be a
  Nova Repeater block, a plain list, or a classic config map" shape, and the
  three places that converted options/rules/search-columns between array and
  delimited-string form, are now backed by shared, single-source-of-truth
  helpers (`RepeaterBlocks::isBlock()`, `DelimitedFormat`) instead of each
  reimplementing the same detection independently.
- `RunCommandRequest` now receives its `CommandRepository` dependency the
  same way a controller action does (method injection on `authorize()`/
  `rules()`), instead of reaching for the container directly — consistent
  with the constructor-DI convention used everywhere else in the package.
- Queued pending history entries now record the built display line and the
  inputs that will be used, so a reload mid-queue still shows what was asked.

### Fixed

- `History::push()` read-modify-write the recent-executions list with no
  locking, so two commands finishing close together (routine with queued or
  concurrent execution) could race and silently drop one entry. It now holds
  an atomic cache lock while updating, the same guarantee
  `ConcurrencyGuard` already provides for command execution itself, and
  degrades to the previous best-effort write on cache stores that can't lock.
- The optional database command source ran a live schema query
  (`information_schema`/`DESCRIBE`) to feature-detect the `enabled` and
  `position` columns on every request, instantiating the model twice per
  check. Both checks are now memoized per instance.
- The last few purge-lottery utilities left in templates (`py-2.5`,
  `gap-1.5`, an `h-3` spacer, `min-w-0`) moved into the package stylesheet —
  the same fractional-size family that host builds have already been caught
  purging (the invisible Run-button icons in 1.1.1).

## [1.1.1] - 2026-07-06

### Fixed

- Text, border and hover colours no longer rely on the host app's Tailwind
  utility classes. Nova apps ship a purged Tailwind build, and which colour
  classes survive the purge (especially `dark:` variants) depends entirely on
  what the host and its other packages happen to use — in a real app this left
  the run modal's field labels invisible in dark mode, history rows without
  separators or a hover highlight, and help text with the wrong contrast. All
  colours now come from the package's own stylesheet, so the tool renders
  identically in every host app and both themes.
- Icon and skeleton sizing no longer relies on Tailwind width/height
  utilities either. The fractional sizes (`w-3.5`, `h-3.5`) are purged from
  most host builds, which collapsed every small inline SVG to zero width —
  most visibly the Run buttons, whose play icon vanished and left the label
  sitting off-centre next to a phantom gap.
- A required `select` variable with no default rendered as an empty box with
  the Run button silently disabled. It now shows a disabled "Choose an
  option…" placeholder until a value is picked. Selects also draw a proper
  dropdown chevron — Nova's form classes strip the native arrow without
  drawing a replacement, leaving a select indistinguishable from a text input.

- History rows' status accent stripe always rendered in the row's text colour
  instead of the status colour (green/red/amber per success/failed/running) —
  the CSS ignored the custom property the row sets.
- The operator attribution ("ran by") on history entries, and the per-user
  rate-limit key, resolved the user through the default auth guard. A panel
  authenticated on a non-default guard (e.g. an "admin" guard set via
  `nova.guard`) recorded no operator at all and rate-limited by IP. Both now
  resolve through Nova's configured guard.

## [1.1.0] - 2026-07-04

### Added

- The bundled `Command` Nova resource now edits variables and flags through
  structured, repeatable sub-forms (Nova's `Repeater` field) instead of raw
  JSON code editors — add/reorder variable blocks, pick the type, fill in
  options (`value:Label` per line), rules (`a|b`) and model-search columns
  (`name,slug`) as plain inputs. Older Nova 4 releases (before 4.24, where
  Repeater was introduced) automatically fall back to the previous JSON
  editors, and the definition parser now accepts list-shaped variables (the
  Repeater's stored shape, or any list of objects carrying a `name`)
  alongside the classic name-keyed map from every command source. Rows
  written in the classic map shape — e.g. seeded from a config file when
  migrating to the database source — open cleanly in the structured editor
  (Nova's stock Repeater preset would crash on them), with a variable's
  implicit defaults (`required`, `type`) made explicit so saving never
  silently flips them.

## [1.0.0] - 2026-07-02

### Added

- A `model` variable type: a searchable, type-ahead field backed by a real
  Eloquent model instead of a plain text input, for commands whose argument
  is a record id picked from a large or dynamic table (e.g. "which Club" out
  of thousands). The backing model must be explicitly allow-listed via the
  new `searchable_models` config key; the search endpoint only ever selects
  the two configured columns, matches case-insensitively regardless of
  database driver, and a submitted value is checked with an `exists` rule
  before the command runs. See "Searchable model variables" in
  the configuration docs.
- Queued commands now record a `pending` entry in History the instant they are
  dispatched, not just once they finish. Previously a queued command that was
  slow to start — or never started, e.g. because no queue worker was consuming
  jobs — left no trace anywhere once the operator reloaded the page or came
  back later; History now shows it immediately and keeps it updated in place.
- Selecting a still-pending or still-running entry from History resumes live
  polling instead of showing a frozen snapshot.
- A "still queued" warning appears in the console after a command has been
  pending for a while, naming the likely cause (no queue worker consuming
  jobs) instead of leaving the operator staring at "Waiting for output…" with
  no information.

- Initial release.
- An explicit `confirm` command option that forces or skips the confirmation
  modal, independent of button type (previously only `danger`/`warning` types
  confirmed).
- Run allow-listed Artisan and shell commands from the Nova dashboard.
- Injection-proof command execution via Symfony Process argument vectors.
- Full compatibility with Laravel Nova v4 and v5.
- Synchronous and queued execution with live, polled output and progress bars.
- Variables (text/select, required and optional) and operator-defined flags.
- Authorization via the tool's `canSee`, a global gate and per-command policies.
- Concurrency control (`without_overlapping`) and per-user rate limiting.
- Cache-backed command history (no database migration).
- `CommandStarted` / `CommandFinished` events for audit logging.
- Pluggable command source via the `CommandSource` contract. The config file is
  the default; an optional database driver (Eloquent model, publishable migration
  and bundled Nova resource) lets commands be managed from the Nova UI, and any
  custom class-string source can be plugged in. All sources pass through the same
  coercion, validation and security model.

- Pest architecture tests enforcing structural invariants: strict types across the
  source, no debug statements, final immutable DTOs, interface-only contracts, and
  a guard proving the command builder never calls a shell function.
- Polished UI: a sticky category rail (with per-group counts) that filters the
  sectioned command list, command search/filter, an optional inline custom-command
  bar, a terminal-style output console (traffic lights, live elapsed timer, blinking
  cursor, auto-scroll, copy), rerun-from-history, relative timestamps and skeleton
  loading. Layout is driven by the package's own stylesheet so it does not depend
  on utility classes that Nova's Tailwind build may purge.
- A richer set of example commands in the default config, spanning several groups
  (Cache, Optimization, Database, Queue, Maintenance).
- Visual polish: type-coloured accent stripes on command cards, per-category
  colours shared between the rail and section headers, a header stat line, a
  gradient tool icon and softer shadows.
- In-repo documentation under `docs/` (configuration, command sources, security,
  authorization, queued execution, and frontend/theming guides).
- UX polish: a two-step inline confirmation for "Clear history" (no native
  dialog, no accidental data loss), the page auto-scrolls to the console when a
  run starts, and truncated command names/run strings/help/categories carry a
  `title` tooltip with the full text.

### Changed

- Static analysis raised to PHPStan level 9. Untrusted config/cache values are now
  narrowed through a dedicated `Cast` value helper instead of blind type casts.
- The database `Command` model is closed to mass assignment with an explicit
  `$fillable` allow-list instead of `$guarded = []`.
- Vue components rewritten with the Composition API (`<script setup>`), using
  `shallowRef` for wholesale-replaced state.
- Feature tests use semantic response assertions (`assertUnprocessable()`,
  `assertForbidden()`, `assertAccepted()`, `assertTooManyRequests()`).
- Risky (danger/warning) commands now confirm through an in-app modal with a
  notice banner instead of a native `window.confirm` dialog.
- CI installs against a local Nova stub, so tests and static analysis run without
  paid Nova credentials (on the repo and on forks).

### Fixed

- Queued executions could hang forever as "pending". A queued custom command now
  carries its definition with the job; a command removed before its job runs, or a
  job that fails (worker timeout/crash), is now marked failed with partial output
  preserved, and a finished result is never overwritten by a late failure.
- Concurrency locks for commands with no timeout (`timeout: 0`) used a short TTL
  that could expire mid-run and allow an overlap; they now use a long TTL.
- Command names and the run-modal title were invisible in dark mode on Nova's
  purged Tailwind build (`dark:text-gray-100` → `dark:text-gray-200`).
- The run modal closed immediately on submit regardless of the outcome, so a
  server-side validation error (a variable's `rules` failing, e.g. `max:255`)
  silently discarded the operator's input behind a generic toast. The modal now
  stays open on failure, shows the real per-field error inline (cleared the
  moment that field is edited again), and only closes once the run actually
  succeeds.
- Polling a queued execution treated *any* failure — a network blip, an expired
  session, an evicted execution record — as "the command finished", freezing the
  console on a stale "Running…" forever with no feedback. Polling now retries
  transient failures a few times, stops immediately (with a clear message) on an
  expired session or a missing execution, and otherwise gives up honestly after a
  few attempts instead of pretending to know the outcome.
- A history row with a long name and a "success"/"failed" badge could push the
  badge past the visible edge of the panel instead of the row truncating —
  another instance of Nova's purged Tailwind build not shipping `min-w-0`.
  Fixed with the same explicit-CSS approach used elsewhere, and hardened
  `.ncr-truncate` and `.ncr-card` generally so this class of bug can't recur.
- A notice banner's `text-transform: capitalize` — meant only to capitalize an
  interpolated word like "danger" — was title-casing entire sentences in
  longer messages (e.g. the new staleness warning). Scoped the capitalization
  to just the interpolated value.

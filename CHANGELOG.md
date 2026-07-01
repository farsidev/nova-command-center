# Changelog

All notable changes to `farsidev/nova-command-center` will be documented in this
file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial release.
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
- Polished UI: command search/filter, an optional inline custom-command bar,
  a terminal-style output console (traffic lights, live elapsed timer, blinking
  cursor, auto-scroll, copy), rerun-from-history, relative timestamps and skeleton
  loading.
- In-repo documentation under `docs/` (configuration, command sources, security,
  authorization, queued execution, and frontend/theming guides).

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

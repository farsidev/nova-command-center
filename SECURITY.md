# Security Policy

Running commands from a web UI is inherently sensitive. This package is designed
so that a compromised or careless *operator input* cannot escalate into
arbitrary command execution.

## Threat model & guarantees

1. **No shell interpolation.** Commands are executed via Symfony Process using an
   **argument vector** (an array). The operating system receives each element as
   a discrete argument; no shell ever parses the input. A variable value such as
   `; rm -rf /`, `$(id)` or `` `whoami` `` is therefore passed verbatim as a
   single argument and cannot spawn a new command.

2. **Placeholder substitution happens after tokenisation.** User values are
   substituted into an already-parsed argument, so a value can only change the
   *content* of one argument — never introduce new arguments, operators, pipes
   or sub-shells.

3. **Allow-list only.** Only commands defined in `config/nova-command-center.php`
   can run. Ad-hoc/custom commands are disabled by default (`custom_commands`).

4. **Shell (bash) execution is disabled by default.** Even when enabled, only
   allow-listed commands run, and shell metacharacters are inert because there is
   no shell. Pipes/redirection are unsupported by design.

5. **Authorization is required.** Requests pass through the tool's `canSee`
   callback, an optional global gate (`runCommand`) and optional per-command
   `can` abilities.

6. **Validation & rate limiting.** Every variable is validated against its
   declared rules before execution, and executions are rate limited per user.

## Operator responsibilities

- Keep the tool visible only to trusted administrators via `canSee`/gates.
- Treat the `commands` allow-list as sensitive configuration.
- Enable `bash` only when you understand the commands you are exposing.

## Reporting a vulnerability

Please **do not** open a public issue for security reports. Email the maintainers
via the security advisory feature on GitHub
(<https://github.com/farsidev/nova-command-center/security/advisories/new>) with:

- a description of the issue and its impact,
- steps to reproduce, and
- any suggested remediation.

You will receive an acknowledgement within a few business days, and we will keep
you informed as the issue is triaged and resolved.

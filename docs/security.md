# Security model

Nova Command Center runs commands on your server, so it is built defensively.
This document describes each layer. To report a vulnerability, see
[SECURITY.md](../SECURITY.md).

## 1. No shell interpolation (the headline guarantee)

User input is **never** concatenated into a shell string. The run string is
tokenised first, then `{name}` placeholders are replaced token-by-token, and the
result is executed through Symfony Process as an argument vector:

```php
new Process([$phpBinary, $basePath.'/artisan', 'cache:forget', $userValue]);
```

A value like `; rm -rf /`, `$(whoami)` or a backtick payload is passed as one
literal argument and is never interpreted by a shell. This is enforced by unit
tests (`CommandBuilderTest`) and an architecture test asserting the builder
never references `shell_exec`, `exec`, `system`, `passthru`, `proc_open` or
`popen`.

## 2. Allow-list only

Only commands you define can run. Ad-hoc "custom" commands are **off by default**
(`custom_commands => []`); enable specific types only if you trust operators to
run them freely, and each run is still gated and logged.

## 3. Bash disabled by default

`bash.enabled => false`. Even when enabled, only allow-listed commands with
`command_type => 'bash'` may run, still as an escaped argument vector.

## 4. Searchable model variables are allow-listed too

A `model` variable's type-ahead search only ever queries an Eloquent class
listed in `searchable_models` (empty by default), and only ever selects the
two configured columns — never the full row. A model class that isn't
allow-listed is never instantiated, even if a misconfigured or
maliciously-edited command definition names one. See [Searchable model
variables](configuration.md#searchable-model-variables).

## 5. Authorization

Every request passes through the `Authorize` middleware, which checks:

- the tool's `canSee()` callback,
- the global `authorize` gate ability (default `runCommand`), and
- an optional per-command `can` ability.

See [authorization](authorization.md).

## 6. Input validation

`RunCommandRequest` validates each submitted variable against its definition
(required/optional, `select` options, custom rules, `model` existence) before
anything runs. Non-scalar or unexpected values are rejected with a 422, not
executed.

## 7. Rate limiting & auditing

The run endpoint is rate limited per user (`rate_limit`). Every execution
dispatches `CommandStarted` and `CommandFinished` events carrying the command,
result and operator — wire these into your audit log.

## Choosing a command source

The `config` source keeps the allow-list in version control and immutable at
runtime — the strongest posture. The `database` source is convenient but moves
that trust into your database; only adopt it behind a strict policy. See
[command sources](command-sources.md) for the full trade-off.

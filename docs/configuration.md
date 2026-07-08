# Configuration

Publish the config file, then edit `config/nova-command-center.php`:

```bash
php artisan vendor:publish --tag=nova-command-center-config
```

## Top-level keys

| Key | Type | Default | Description |
| --- | ---- | ------- | ----------- |
| `navigation_label` | string | `'Command Center'` | Sidebar label and page heading. |
| `help` | string | … | Help text shown under the heading. |
| `source` | array | `['driver' => 'config', …]` | Where commands are read from. See [command sources](command-sources.md). |
| `history` | int | `15` | Number of recent executions to keep (cache-backed). `0` disables history. |
| `cache_store` | string\|null | `null` | Cache store for history, live output and locks. `null` uses the app default. A lock-capable store (redis, memcached, database) is required for `without_overlapping`. |
| `authorize` | string\|null | `'runCommand'` | Gate ability checked on every request. `null` relies solely on the tool's `canSee()`. See [authorization](authorization.md). |
| `rate_limit` | int\|null | `30` | Max executions per authenticated user per minute. `null` disables it. The user — for the limit key and for History's "ran by" attribution — is resolved through Nova's configured guard (`nova.guard`), so panels on a non-default guard attribute correctly. |
| `bash` | array | disabled | Shell command settings — see below. |
| `custom_commands` | list | `[]` | Command types operators may type ad-hoc. Empty = off. |
| `searchable_models` | list | `[]` | Eloquent model classes allowed to back a `model` variable. Empty = no model variable can be searched. See [Searchable model variables](#searchable-model-variables). |
| `without_overlapping` | array | `[]` | Concurrency locks — see below. |
| `defaults` | array | see below | Fallback `timeout`, `output_size` and button `type`. |
| `commands` | array | example set | The allow-list, keyed by display name. |

## `bash`

Shell execution is **off by default**. Even when enabled, only allow-listed
commands with `command_type => 'bash'` can run, and arguments are always passed
as an escaped argument vector — never interpolated into a shell string.

```php
'bash' => [
    'enabled' => false,
    'working_directory' => null, // defaults to the app base path
    'env' => [],                 // extra env vars merged into the process
],
```

## `without_overlapping`

Prevent a command — or any command in a group — from running concurrently.
Requires a lock-capable cache store.

```php
'without_overlapping' => [
    'commands' => ['migrate'],        // by run string
    'groups' => ['Database'],         // by command group
],
```

## `defaults`

```php
'defaults' => [
    'timeout' => 60,       // seconds before the process is killed
    'output_size' => 25,   // trailing output lines shown
    'type' => 'primary',   // default button style
],
```

## A command definition

Commands are keyed by display name; only `run` is required. Every other key is
optional and falls back to a safe default.

```php
'Run Migrations' => [
    'run' => 'migrate',
    'command_type' => 'artisan',   // 'artisan' (default) or 'bash'
    'group' => 'Database',
    'type' => 'danger',            // button style
    'help' => 'Apply pending migrations.',
    'timeout' => 120,
    'output_size' => 50,
    'queue' => false,              // true, or ['connection' => …, 'queue' => …]
    'can' => 'runMigrations',      // optional gate ability for this command
    'confirm' => true,             // force/skip the confirmation modal; default: danger/warning types confirm, others don't
    'variables' => [/* … */],      // see below
    'flags' => [
        ['label' => 'Force in production', 'flag' => '--force', 'default' => true],
    ],
],
```

### Variables

Referenced in `run` with `{name}` placeholders. Substitution happens **after**
the command is tokenised, so a value is always a single argument.

```php
'variables' => [
    'key' => [
        'label' => 'Cache key',
        'type' => 'text',          // 'text' (default), 'select' or 'model'
        'options' => [],           // for select: ['a' => 'Label', …] or [['value','label']]
        'required' => true,
        'default' => null,
        'rules' => ['string', 'max:255'],
        'help' => null,
        'placeholder' => null,
    ],
],
```

Shorthand forms are accepted: `'variables' => ['key', 'value']` (a list of
names) or `'key' => 'Label'` (name → label).

A required `select` with no `default` renders with a disabled *"Choose an
option…"* placeholder, and the Run button stays disabled until every required
variable has a value. An optional `select` gets an explicit empty ("—")
choice instead.

### Searchable model variables

Some commands take an id that isn't practical to guess or pick from a static
dropdown — e.g. "which Club" out of a few thousand rows. A `model` variable
renders as a type-ahead search box instead of a plain text input, backed by a
real Eloquent model, and submits the matched record's id.

```php
'searchable_models' => [
    \App\Models\Club::class,
],

// …

'variables' => [
    'club' => [
        'label' => 'Club',
        'type' => 'model',
        'model' => \App\Models\Club::class,   // must appear in searchable_models above
        'value_column' => 'id',               // default: 'id' — submitted as the variable's value
        'label_column' => 'name',             // default: 'name' — shown in the results list
        'search_columns' => ['name', 'slug'], // default: [label_column] — columns matched against the query
    ],
],
```

A `default` on a `model` variable is stored as the raw value (e.g. an id) —
the field resolves it to a label from the same search endpoint the moment the
run modal opens, so the operator sees a name instead of a bare id. If the
value no longer matches any row (e.g. the referenced record was deleted), the
field falls back to showing the raw value.

Matching against `search_columns` is always case-insensitive, regardless of
database driver — including on drivers like PostgreSQL where `LIKE` is
case-sensitive by default, and on JSON-typed columns (e.g. a translatable
column stored as `jsonb`, matched as its raw JSON text).

**Security.** A `model` class is only searchable once it is explicitly listed
in `searchable_models` — the same allow-list posture as `bash` and
`custom_commands`. The search endpoint (`GET
.../commands/{command}/variables/{variable}/search?q=`, or `?value=` to
resolve one known value to its label instead of running a free-text search)
only ever selects
`value_column` and `label_column` from the table — never the full row — so it
cannot be used to read unrelated columns, and it is gated behind the same
authorization as running the command itself (the tool's gate, plus the
command's own `can` ability, if set). A submitted value is additionally
checked with an `exists:table,column` rule before the command runs, so a
tampered id is rejected rather than silently passed through. Anyone who can
run the command can search the allow-listed model's `label_column` and
`search_columns` — don't allow-list a model whose display columns you
wouldn't want a lower-privileged operator to search by.

### Flags

Rendered as checkboxes. When checked, the `flag` string is appended as its own
argument.

```php
'flags' => [
    [
        'label' => 'Force',
        'flag' => '--force',
        'default' => true,
        'help' => 'Skip the confirmation prompt the underlying command would otherwise show.',
    ],
],
```

Shorthand forms are accepted too: `'--force' => 'Force'` or `'Force' =>
'--force'` (`help` isn't available in shorthand — use the array form to set it).

## Migrating from other command-runner packages

The `run` string is executed exactly as written — every token in it must be a
real argument the target command understands. This package does not scan the
`run` string for special tokens of its own.

Some other Nova command-runner packages do the opposite: they let you embed a
control flag like `--should-queue` directly in the run string and strip it out
before executing, using it purely as a signal to that package (not the command
being run). If you copy a `run` string from one of those configs verbatim, that
token gets passed straight through to the real command, which will reject it
with something like:

```
The "--should-queue" option does not exist.
```

To migrate such a command, remove the magic token from `run` and use this
package's own `queue` key instead:

```php
// Before (another package's config):
'run' => 'my:command --should-queue',

// After:
'run' => 'my:command',
'queue' => true,
```

Only remove the token if the target command does not itself define that option
— check its `$signature`. A command can legitimately have its own real
`--should-queue` (or similarly named) option with completely different meaning;
in that case leave it in `run` untouched.

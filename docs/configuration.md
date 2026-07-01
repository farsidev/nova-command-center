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
| `rate_limit` | int\|null | `30` | Max executions per authenticated user per minute. `null` disables it. |
| `bash` | array | disabled | Shell command settings — see below. |
| `custom_commands` | list | `[]` | Command types operators may type ad-hoc. Empty = off. |
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
        'type' => 'text',          // 'text' (default) or 'select'
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

### Flags

Rendered as checkboxes. When checked, the `flag` string is appended as its own
argument.

```php
'flags' => [
    ['label' => 'Force', 'flag' => '--force', 'default' => true],
],
```

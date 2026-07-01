# Nova Command Center

[![Tests](https://github.com/farsidev/nova-command-center/actions/workflows/tests.yml/badge.svg)](https://github.com/farsidev/nova-command-center/actions/workflows/tests.yml)
[![Static Analysis](https://github.com/farsidev/nova-command-center/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/farsidev/nova-command-center/actions/workflows/static-analysis.yml)
[![Latest Version](https://img.shields.io/packagist/v/farsidev/nova-command-center.svg)](https://packagist.org/packages/farsidev/nova-command-center)
[![License](https://img.shields.io/packagist/l/farsidev/nova-command-center.svg)](LICENSE.md)

Run pre-approved Artisan and shell commands directly from your Laravel Nova
dashboard — safely. Built for and tested against **Nova v4 and v5**.

This package is a security-first, clean-room reimagining of the command-center
idea. It fixes the long-standing problems of earlier tools: shell injection,
Nova 5 incompatibility (`__ is not defined`), null-value crashes, missing
optional variables and the absence of authorization hooks.

---

## Highlights

- 🔒 **Injection-proof by design.** User input is never interpolated into a
  shell string. Commands run through Symfony Process as an argument vector, so a
  value like `; rm -rf /` is passed as one literal argument and nothing else.
- ✅ **Allow-list only.** Only commands you define in config can run. Free-form
  commands and shell (`bash`) execution are **off by default**.
- 🧩 **Nova 4 & 5 compatible.** One code path, Laravel Mix build, and a
  translation shim that survives Nova 5 removing the global `__` helper.
- 🧵 **Sync & queued execution** with live, polled output and progress bars.
- 🛡️ **Authorization** via a gate and optional per-command policies.
- 🕓 **History** without a database migration.
- 🔧 **Variables & flags**, including optional variables and `select` inputs.
- 🚦 **Concurrency control** (`without_overlapping`) and **rate limiting**.

---

## Requirements

| Package      | Version                    |
| ------------ | -------------------------- |
| PHP          | 8.1+                       |
| Laravel      | 10, 11 or 12               |
| Laravel Nova | 4.x or 5.x                 |

## Installation

```bash
composer require farsidev/nova-command-center
```

Register the tool in `app/Providers/NovaServiceProvider.php`:

```php
use Farsidev\NovaCommandCenter\CommandCenter;

public function tools(): array
{
    return [
        (new CommandCenter)->canSee(function ($request) {
            return $request->user()?->isAdmin() ?? false;
        }),
    ];
}
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=nova-command-center-config
```

## Configuration

All commands live in `config/nova-command-center.php`. A command is keyed by its
display name; only `run` is required.

```php
'commands' => [

    'Clear Application Cache' => [
        'run' => 'cache:clear',
        'group' => 'Cache',
        'type' => 'warning',
        'help' => 'Flush the application cache.',
    ],

    'Forget Cache Key' => [
        'run' => 'cache:forget {key}',
        'group' => 'Cache',
        'variables' => [
            'key' => [
                'label' => 'Cache key',
                'type' => 'text',
                'required' => true,
                'rules' => ['string', 'max:255'],
            ],
        ],
    ],

    'Run Migrations' => [
        'run' => 'migrate',
        'group' => 'Database',
        'type' => 'danger',
        'flags' => [
            ['label' => 'Force in production', 'flag' => '--force', 'default' => true],
        ],
    ],

],
```

### Command options

| Key            | Type            | Description                                              |
| -------------- | --------------- | ------------------------------------------------------- |
| `run`          | string          | Command to run. Use `{name}` placeholders for variables. |
| `command_type` | `artisan`/`bash`| Defaults to `artisan`.                                   |
| `group`        | string          | UI grouping.                                             |
| `type`         | string          | Button style: `primary`, `danger`, `warning`, …         |
| `help`         | string          | Description shown under the command.                     |
| `timeout`      | int             | Max seconds before the process is killed.                |
| `output_size`  | int             | Number of trailing output lines to display.             |
| `queue`        | bool / array    | Run on the queue. Array may set `connection` / `queue`.  |
| `can`          | string          | Gate ability required to run this specific command.      |
| `variables`    | array           | User input, keyed by placeholder name (see below).       |
| `flags`        | array           | Optional flags rendered as checkboxes.                   |

### Variables

Variables are referenced in `run` with `{name}` placeholders. Because
substitution happens **after** the command is tokenised, a variable can only
ever become the content of a single argument — never a new one.

```php
'variables' => [
    'email' => [
        'label' => 'User email',
        'type' => 'text',            // or 'select'
        'required' => false,         // optional variables are fully supported
        'default' => null,
        'options' => [               // for 'select'
            ['value' => 'daily', 'label' => 'Daily'],
            ['value' => 'weekly', 'label' => 'Weekly'],
        ],
        'rules' => ['email'],        // extra Laravel validation rules
    ],
],
```

An optional variable that is left blank simply removes its placeholder token,
so `foo --tag={tag}` becomes `foo` when `tag` is empty.

### Authorization

Every request is checked against the tool's `canSee` callback. In addition, you
may define a global gate ability (default `runCommand`) and/or per-command `can`
abilities:

```php
// AuthServiceProvider
Gate::define('runCommand', fn ($user) => $user->isAdmin());
Gate::define('deploy', fn ($user) => $user->isOwner());
```

```php
// config
'authorize' => 'runCommand',
'commands' => [
    'Deploy' => ['run' => 'deploy:run', 'can' => 'deploy'],
],
```

### Shell (bash) commands

Shell execution is **disabled by default**. When enabled, only allow-listed
commands run, and arguments are always escaped. Shell features such as pipes and
redirection are intentionally unsupported — wrap those in a script file instead.

```php
'bash' => ['enabled' => true],

'commands' => [
    'Disk Usage' => ['run' => 'df -h', 'command_type' => 'bash', 'group' => 'System'],
],
```

### Queued execution & progress bars

Mark a command as `queue => true` to run it on a worker with live, polled output.
To report progress from your own Artisan command, use the provided trait:

```php
use Farsidev\NovaCommandCenter\Concerns\InteractsWithProgress;

class RebuildSearchIndex extends Command
{
    use InteractsWithProgress;

    public function handle(): int
    {
        $this->novaProgressStart($items->count());

        foreach ($items as $item) {
            // ...
            $this->novaProgressAdvance();
        }

        $this->novaProgressFinish('Done');

        return self::SUCCESS;
    }
}
```

## Events

Every execution dispatches `Farsidev\NovaCommandCenter\Events\CommandStarted` and
`CommandFinished`, each carrying the command definition, the execution result and
the operator — handy for audit logging.

## Security

See [SECURITY.md](SECURITY.md) for the threat model and how to report a
vulnerability. In short: allow-list only, no shell interpolation, bash off by
default, authorization required, and every value is validated before it runs.

## Development

The frontend is built with Laravel Mix (the build system Nova uses on both v4
and v5):

```bash
npm install
npm run dev      # or: npm run watch / npm run prod
```

Backend quality tools:

```bash
composer test      # Pest
composer analyse   # PHPStan
composer lint      # Pint (dry run)
```

> Nova is a paid, private package. Running the test suite locally requires Nova
> credentials, or the provided `composer.testing.json` scaffold that swaps in a
> lightweight stub. See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

The MIT License. See [LICENSE.md](LICENSE.md).

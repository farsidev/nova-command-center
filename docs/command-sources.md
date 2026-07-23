# Command sources

Where the allow-list of commands is read from is pluggable. The contract is one
method:

```php
namespace Farsi\NovaCommandCenter\Contracts;

interface CommandSource
{
    /** @return iterable<array-key, mixed> name => raw definition */
    public function definitions(): iterable;
}
```

Whatever the source, every raw definition still flows through
`CommandDefinition::fromConfig()` — the same coercion, validation and security
model applies. **A custom source can never widen the trust boundary.**

Select the driver in `config/nova-command-center.php`:

```php
'source' => [
    'driver' => 'config', // 'config' | 'database' | Custom::class
    'model'  => \Farsi\NovaCommandCenter\Models\Command::class,
],
```

## `config` (default, recommended)

Commands live in the `commands` array of the config file. Version-controlled,
reviewed in pull requests, and immutable at runtime. This is the safest posture
and requires no database.

## `database`

Store commands in the `nova_command_center_commands` table and manage them from
a bundled Nova resource.

> ⚠️ **Security trade-off.** This moves the allow-list out of version control.
> Anyone who can create or edit those rows decides what the tool runs — that is
> remote code execution by design. Gate the resource behind a strict policy,
> keep bash disabled unless required, and remember every run still emits audit
> events. If you don't need UI-managed commands, stay on `config`.

1. Publish and run the migration:

   ```bash
   php artisan vendor:publish --tag=nova-command-center-migrations
   php artisan migrate
   ```

2. Switch the driver:

   ```php
   'source' => ['driver' => 'database'],
   ```

3. Register the resource from your `NovaServiceProvider`, ideally behind a policy:

   ```php
   use Farsi\NovaCommandCenter\Nova\Command;

   Nova::resources([Command::class]);
   ```

Each row maps one-to-one onto the config keys (`run`, `command_type`, `group`,
`type`, `help`, `timeout`, `output_size`, `queue`, `can`, `confirm`, `variables`,
`flags`) plus `enabled` (bool) and `position` (int) to toggle and order commands.
The model is closed to mass assignment with an explicit `$fillable` list.

`confirm` is a nullable boolean that mirrors the config key: `null` keeps the
type-based default (danger/warning ask, others don't), `true` always asks, and
`false` never asks. The Nova resource exposes it as a three-option select.

If you already published an older create migration without `confirm`, publish
again and run migrations — an additive `add_confirm_…` migration is included
for existing installs.

### Editing variables and flags

On Nova 4.24+ the resource edits variables and flags through structured,
repeatable sub-forms (Nova's `Repeater` field) — add a variable block, pick
its type (`text`, `select` or `model`), fill in labels, options, validation
rules and the model-search columns, and reorder blocks by dragging. No JSON
required. On older Nova 4 releases the resource falls back to raw JSON code
editors automatically.

Inside a variable block:

- **Options** (for `select`) take one option per line, as `value:Label` or
  just `value` — e.g. `DK:Denmark`.
- **Rules** are pipe-separated, e.g. `string|max:255`.
- **Search columns** (for `model`) are comma-separated, e.g. `name,slug`.
- A `model` variable's class must still be allow-listed in the config file's
  `searchable_models` — that boundary is deliberately not editable from the UI.

All of these string shorthands are also accepted in the config file and in
hand-written JSON, so definitions can be copied freely between sources. The
parser accepts variables as a name-keyed map (the config-file idiom), a plain
list of objects carrying a `name`, or the Repeater's own stored shape.

### Using your own model

Point the driver at any Eloquent model. If it exposes a `toDefinition(): array`
method the source uses it; otherwise it falls back to `toArray()`.

```php
'source' => ['driver' => 'database', 'model' => App\Models\Runbook::class],
```

## Custom source

Any class implementing `CommandSource` can be bound by class-string. Useful for
YAML files, a remote registry, or feature-flagged command sets.

```php
use Farsi\NovaCommandCenter\Contracts\CommandSource;

final class YamlCommandSource implements CommandSource
{
    public function definitions(): iterable
    {
        return yaml_parse_file(base_path('commands.yaml'));
    }
}
```

```php
// config/nova-command-center.php
'source' => ['driver' => YamlCommandSource::class],
```

The class is resolved from the container, so you can type-hint dependencies in
its constructor.

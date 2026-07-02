# Authorization

Access is checked at three layers, all enforced by the `Authorize` middleware on
every request to the tool.

## 1. Tool visibility — `canSee()`

Controls whether the tool appears and is reachable at all. Set it when
registering the tool:

```php
use Farsi\NovaCommandCenter\CommandCenter;

public function tools(): array
{
    return [
        (new CommandCenter)->canSee(function ($request) {
            return $request->user()?->isAdmin() ?? false;
        }),
    ];
}
```

## 2. Global gate — `authorize`

Every request is also checked against the gate ability named by the `authorize`
config key (default `runCommand`). Define it in your `AuthServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('runCommand', function ($user) {
    return $user->hasRole('operator');
});
```

Set `'authorize' => null` to skip the gate and rely solely on `canSee()`.

## 3. Per-command policy — `can`

Any command may declare its own ability, checked in addition to the global gate.
Use it to restrict individual high-risk commands:

```php
'Run Migrations' => [
    'run' => 'migrate',
    'can' => 'runMigrations',
],
```

```php
Gate::define('runMigrations', function ($user) {
    return $user->isSuperAdmin();
});
```

The command receives the `CommandDefinition` as the gate argument, so you can
authorize based on its group, type or name. A denied per-command ability returns
`403`; an unknown command returns `422`.

## Database source

If you enable the [database command source](command-sources.md), also protect
the bundled Nova resource with a policy — whoever can edit those rows defines
what the tool runs.

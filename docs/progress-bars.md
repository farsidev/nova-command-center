# Queued execution & progress bars

## Running on the queue

Mark a command to run on the queue with the `queue` key:

```php
'Rebuild Search Index' => [
    'run' => 'scout:import "App\\Models\\Post"',
    'queue' => true, // or ['connection' => 'redis', 'queue' => 'long-running']
],
```

Queued commands return immediately with a `202 Accepted` and a pending
execution. The UI then polls the execution endpoint and streams output into the
console as it arrives — the same live view as synchronous runs, without holding
the request open. Use this for anything long-running or anything you don't want
tied to the HTTP timeout.

A run-mode toggle also lets operators choose sync vs queue per run where allowed.

## Reporting progress from your command

Any Artisan command launched by Command Center can report a live progress bar
back to the UI using the `InteractsWithProgress` trait. When the package runs a
command it exposes the execution id to the child process, which the trait picks
up automatically.

```php
use Farsi\NovaCommandCenter\Concerns\InteractsWithProgress;
use Illuminate\Console\Command;

class ImportUsers extends Command
{
    use InteractsWithProgress;

    protected $signature = 'users:import';

    public function handle(): int
    {
        $users = User::query()->lazy();

        $this->novaProgressStart(User::count(), 'Importing users…');

        foreach ($users as $user) {
            // … do the work …
            $this->novaProgressAdvance();
        }

        $this->novaProgressFinish('Import complete');

        return self::SUCCESS;
    }
}
```

### Trait API

| Method | Description |
| ------ | ----------- |
| `novaProgressStart(int $total, ?string $message = null)` | Begin, setting the total step count. |
| `novaProgressAdvance(int $step = 1, ?string $message = null)` | Advance by `$step`. |
| `novaProgressSet(int $current, ?string $message = null)` | Jump to an absolute position. |
| `novaProgressFinish(?string $message = null)` | Complete (fills the bar). |

Each call writes `current`, `total`, a computed `percentage` and an optional
`message` to the execution store; the UI renders them as a progress bar with the
message beneath it. The calls are inert when the command is run outside Command
Center (e.g. from cron), so the trait is safe to leave in place permanently.

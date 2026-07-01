<?php

declare(strict_types=1);

use Farsidev\NovaCommandCenter\Support\CommandRepository;

function commandId(string $name): string
{
    foreach (app(CommandRepository::class)->visible() as $command) {
        if ($command->name === $name) {
            return $command->id;
        }
    }

    throw new RuntimeException("Unknown command [{$name}].");
}

it('lists commands and configuration', function () {
    $response = $this->getJson('_ncr/commands');

    $response->assertOk()
        ->assertJsonCount(5, 'commands')
        ->assertJsonPath('config.bash_enabled', false)
        ->assertJsonPath('config.history_enabled', true);
});

it('runs a command synchronously via the API', function () {
    $response = $this->postJson('_ncr/commands/run', ['command' => commandId('Clear Cache')]);

    $response->assertOk()
        ->assertJsonPath('queued', false)
        ->assertJsonPath('execution.status', 'success');

    expect($this->executor->arguments[1])->toBe(base_path('artisan'))
        ->and(array_slice($this->executor->arguments, 2))->toBe(['cache:clear']);
});

it('validates required variables', function () {
    $response = $this->postJson('_ncr/commands/run', ['command' => commandId('Forget Key')]);

    $response->assertUnprocessable()->assertJsonValidationErrors('variables.key');
});

it('passes a validated variable through safely', function () {
    $response = $this->postJson('_ncr/commands/run', [
        'command' => commandId('Forget Key'),
        'variables' => ['key' => '; rm -rf /'],
    ]);

    $response->assertOk();

    expect(array_slice($this->executor->arguments, 2))->toBe(['cache:forget', '; rm -rf /']);
});

it('appends a checked flag', function () {
    $response = $this->postJson('_ncr/commands/run', [
        'command' => commandId('Migrate'),
        'flags' => ['force' => true],
    ]);

    $response->assertOk();

    expect(array_slice($this->executor->arguments, 2))->toBe(['migrate', '--force']);
});

it('rejects a bash command over the API while bash is disabled', function () {
    $response = $this->postJson('_ncr/commands/run', ['command' => commandId('Disk Usage')]);

    $response->assertUnprocessable();
});

it('enforces the rate limit', function () {
    config()->set('nova-command-center.rate_limit', 1);

    $this->postJson('_ncr/commands/run', ['command' => commandId('Clear Cache')])->assertOk();
    $this->postJson('_ncr/commands/run', ['command' => commandId('Clear Cache')])->assertTooManyRequests();
});

it('returns 422 for an unknown command', function () {
    $this->postJson('_ncr/commands/run', ['command' => 'does-not-exist'])->assertUnprocessable();
});

<?php

declare(strict_types=1);

it('rejects a custom command when custom types are disabled', function () {
    // custom_commands is empty by default in the test environment.
    $this->postJson('_ncr/commands/run', [
        'custom' => ['type' => 'artisan', 'run' => 'migrate'],
    ])->assertUnprocessable();
});

it('runs a custom command when its type is allow-listed', function () {
    config()->set('nova-command-center.custom_commands', ['artisan']);
    $this->refreshCommands();

    $this->postJson('_ncr/commands/run', [
        'custom' => ['type' => 'artisan', 'run' => 'inspire'],
    ])->assertOk()->assertJsonPath('execution.status', 'success');

    expect(array_slice($this->executor->arguments, 2))->toBe(['inspire']);
});

it('never lets a custom bash command bypass the bash switch', function () {
    config()->set('nova-command-center.custom_commands', ['bash']);
    config()->set('nova-command-center.bash.enabled', false);
    $this->refreshCommands();

    $this->postJson('_ncr/commands/run', [
        'custom' => ['type' => 'bash', 'run' => 'rm -rf /'],
    ])->assertUnprocessable();
});

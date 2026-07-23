<?php

declare(strict_types=1);

use Farsi\NovaCommandCenter\Actions\ExecuteCommand;
use Farsi\NovaCommandCenter\Support\History;

it('lists history over the API', function () {
    app(ExecuteCommand::class)->handle(command('Clear Cache'), [], []);

    $this->getJson('_ncr/history')
        ->assertOk()
        ->assertJsonCount(1, 'history')
        ->assertJsonPath('history.0.name', 'Clear Cache');
});

it('clears history over the API', function () {
    app(ExecuteCommand::class)->handle(command('Clear Cache'), [], []);

    $this->deleteJson('_ncr/history')->assertOk()->assertJsonCount(0, 'history');

    expect(app(History::class)->all())->toHaveCount(0);
});

it('caps history at the configured size', function () {
    config()->set('nova-command-center.history', 2);
    app()->forgetInstance(History::class);

    foreach (range(1, 4) as $ignored) {
        app(ExecuteCommand::class)->handle(command('Clear Cache'), [], []);
    }

    expect(app(History::class)->all())->toHaveCount(2);
});

it('persists the resolved inputs used for a run', function () {
    $result = app(ExecuteCommand::class)->handle(
        command('Forget Key'),
        ['key' => 'users:42'],
        [],
    );

    expect($result->variables)->toBe(['key' => 'users:42'])
        ->and($result->flags)->toBe([]);

    $this->getJson('_ncr/history')
        ->assertOk()
        ->assertJsonPath('history.0.variables.key', 'users:42')
        ->assertJsonPath('history.0.flags', []);
});

it('persists enabled flag strings for a run', function () {
    $result = app(ExecuteCommand::class)->handle(
        command('Migrate'),
        [],
        ['--force'],
    );

    expect($result->flags)->toBe(['--force']);

    $this->getJson('_ncr/history')
        ->assertOk()
        ->assertJsonPath('history.0.flags', ['--force']);
});

it('returns stored inputs when a command is run over the API', function () {
    $this->postJson('_ncr/commands/run', [
        'command' => commandId('Forget Key'),
        'variables' => ['key' => 'sessions'],
    ])
        ->assertOk()
        ->assertJsonPath('execution.variables.key', 'sessions');
});

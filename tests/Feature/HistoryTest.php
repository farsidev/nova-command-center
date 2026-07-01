<?php

declare(strict_types=1);

use Farsidev\NovaCommandCenter\Actions\ExecuteCommand;
use Farsidev\NovaCommandCenter\Support\History;

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

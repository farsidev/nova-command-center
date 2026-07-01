<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    config()->set('nova-command-center.commands', [
        'Guarded' => ['run' => 'cache:clear', 'can' => 'ncr-guarded'],
    ]);
    $this->refreshCommands();
});

it('forbids a command whose gate ability denies', function () {
    Gate::define('ncr-guarded', fn ($user = null) => false);

    $id = commandId('Guarded');

    $this->postJson('_ncr/commands/run', ['command' => $id])->assertStatus(403);
});

it('allows a command whose gate ability passes', function () {
    Gate::define('ncr-guarded', fn ($user = null) => true);

    $id = commandId('Guarded');

    $this->postJson('_ncr/commands/run', ['command' => $id])
        ->assertOk()
        ->assertJsonPath('execution.status', 'success');
});

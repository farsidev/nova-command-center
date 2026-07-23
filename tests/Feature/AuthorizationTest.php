<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    config()->set('nova-command-center.commands', [
        'Open' => ['run' => 'cache:clear', 'group' => 'Cache'],
        'Guarded' => ['run' => 'cache:clear', 'can' => 'ncr-guarded', 'group' => 'Cache'],
    ]);
    $this->refreshCommands();
});

it('forbids a command whose gate ability denies', function () {
    Gate::define('ncr-guarded', fn ($user = null) => false);

    $id = commandId('Guarded');

    $this->postJson('_ncr/commands/run', ['command' => $id])->assertForbidden();
});

it('allows a command whose gate ability passes', function () {
    Gate::define('ncr-guarded', fn ($user = null) => true);

    $id = commandId('Guarded');

    $this->postJson('_ncr/commands/run', ['command' => $id])
        ->assertOk()
        ->assertJsonPath('execution.status', 'success');
});

it('hides commands the operator cannot run from the catalogue', function () {
    Gate::define('ncr-guarded', fn ($user = null) => false);

    $this->getJson('_ncr/commands')
        ->assertOk()
        ->assertJsonCount(1, 'commands')
        ->assertJsonPath('commands.0.name', 'Open');
});

it('lists a gated command when the ability allows', function () {
    Gate::define('ncr-guarded', fn ($user = null) => true);

    $names = collect($this->getJson('_ncr/commands')->json('commands'))->pluck('name');

    expect($names)->toContain('Open')->toContain('Guarded');
});

<?php

declare(strict_types=1);

use Farsi\NovaCommandCenter\Contracts\CommandSource;
use Farsi\NovaCommandCenter\Tests\Fakes\TestClub;
use Illuminate\Support\Facades\Gate;

function configureCommands(array $commands): void
{
    config()->set('nova-command-center.commands', $commands);
    test()->refreshCommands();
    app()->forgetInstance(CommandSource::class);
}

it('passes a healthy configuration', function () {
    configureCommands([
        'Clear Cache' => ['run' => 'cache:clear'],
        'Forget Key' => [
            'run' => 'cache:forget {key}',
            'variables' => ['key' => ['label' => 'Key']],
        ],
    ]);

    $this->artisan('nova-command-center:check')
        ->expectsOutputToContain('Clear Cache')
        ->expectsOutputToContain('0 errors')
        ->assertSuccessful();
});

it('fails when a command has no run string', function () {
    configureCommands(['Broken' => ['group' => 'X']]);

    $this->artisan('nova-command-center:check')
        ->expectsOutputToContain('dropped')
        ->assertFailed();
});

it('fails when a placeholder has no matching variable', function () {
    configureCommands(['Typo' => ['run' => 'cache:forget {kye}', 'variables' => ['key' => 'Key']]]);

    $this->artisan('nova-command-center:check')
        ->expectsOutputToContain('{kye}')
        ->assertFailed();
});

it('notes a variable that no placeholder references without failing', function () {
    configureCommands(['Loose' => ['run' => 'inspire', 'variables' => ['mood' => 'Mood']]]);

    $this->artisan('nova-command-center:check')
        ->expectsOutputToContain('appended')
        ->assertSuccessful();
});

it('fails for a required select with no options and no default', function () {
    configureCommands([
        'Pick' => [
            'run' => 'pick {choice}',
            'variables' => ['choice' => ['label' => 'Choice', 'type' => 'select', 'required' => true]],
        ],
    ]);

    $this->artisan('nova-command-center:check')
        ->expectsOutputToContain('can never be enabled')
        ->assertFailed();
});

it('fails when a model variable is not allow-listed', function () {
    configureCommands([
        'Sync Club' => [
            'run' => 'club:sync {club}',
            'variables' => ['club' => ['label' => 'Club', 'type' => 'model', 'model' => TestClub::class]],
        ],
    ]);

    $this->artisan('nova-command-center:check')
        ->expectsOutputToContain('searchable_models')
        ->assertFailed();
});

it('fails when a model variable points at a class that does not exist', function () {
    configureCommands([
        'Ghost' => [
            'run' => 'ghost {thing}',
            'variables' => ['thing' => ['label' => 'Thing', 'type' => 'model', 'model' => 'App\\Missing\\Thing']],
        ],
    ]);

    $this->artisan('nova-command-center:check')
        ->expectsOutputToContain('does not exist')
        ->assertFailed();
});

it('warns about a bash command while bash is disabled', function () {
    configureCommands(['Disk' => ['run' => 'df -h', 'command_type' => 'bash']]);

    $this->artisan('nova-command-center:check')
        ->expectsOutputToContain('bash execution is disabled')
        ->assertSuccessful();
});

it('treats warnings as failures under --strict', function () {
    configureCommands(['Disk' => ['run' => 'df -h', 'command_type' => 'bash']]);

    $this->artisan('nova-command-center:check', ['--strict' => true])->assertFailed();
});

it('warns when a per-command gate ability is not defined', function () {
    configureCommands(['Guarded' => ['run' => 'inspire', 'can' => 'undefined-ability']]);

    $this->artisan('nova-command-center:check')
        ->expectsOutputToContain('undefined-ability')
        ->assertSuccessful();
});

it('does not warn when the gate ability exists', function () {
    Gate::define('run-inspire', fn ($user = null) => true);

    configureCommands(['Guarded' => ['run' => 'inspire', 'can' => 'run-inspire']]);

    $this->artisan('nova-command-center:check')
        ->expectsOutputToContain('0 warnings')
        ->assertSuccessful();
});

it('warns when the database source table is missing', function () {
    config()->set('nova-command-center.source', ['driver' => 'database']);
    configureCommands([]);

    $this->artisan('nova-command-center:check')->assertFailed();
});

it('warns when no commands are defined at all', function () {
    configureCommands([]);

    $this->artisan('nova-command-center:check')
        ->expectsOutputToContain('No commands are defined')
        ->assertSuccessful();
});

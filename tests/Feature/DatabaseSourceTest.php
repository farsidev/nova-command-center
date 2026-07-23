<?php

declare(strict_types=1);

use Farsi\NovaCommandCenter\Contracts\CommandSource;
use Farsi\NovaCommandCenter\Models\Command;
use Farsi\NovaCommandCenter\Support\CommandRepository;
use Farsi\NovaCommandCenter\Support\Sources\DatabaseCommandSource;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('nova_command_center_commands', function ($table): void {
        $table->id();
        $table->string('name')->unique();
        $table->text('run');
        $table->string('command_type')->default('artisan');
        $table->string('group')->default('General');
        $table->string('type')->default('primary');
        $table->text('help')->nullable();
        $table->unsignedInteger('timeout')->nullable();
        $table->unsignedInteger('output_size')->nullable();
        $table->json('queue')->nullable();
        $table->string('can')->nullable();
        $table->boolean('confirm')->nullable();
        $table->json('variables')->nullable();
        $table->json('flags')->nullable();
        $table->boolean('enabled')->default(true);
        $table->unsignedInteger('position')->default(0);
        $table->timestamps();
    });

    config()->set('nova-command-center.source', [
        'driver' => 'database',
        'model' => Command::class,
    ]);

    $this->app->forgetInstance(CommandSource::class);
    $this->refreshCommands();
});

afterEach(function () {
    Schema::dropIfExists('nova_command_center_commands');
});

it('binds the database source when the driver is "database"', function () {
    expect($this->app->make(CommandSource::class))->toBeInstanceOf(DatabaseCommandSource::class);
});

it('loads command definitions from the database table', function () {
    Command::create([
        'name' => 'Clear Cache',
        'run' => 'cache:clear',
        'group' => 'Cache',
        'type' => 'warning',
        'position' => 1,
    ]);

    Command::create([
        'name' => 'Forget Key',
        'run' => 'cache:forget {key}',
        'group' => 'Cache',
        'variables' => ['key' => ['label' => 'Key', 'required' => true]],
        'position' => 2,
    ]);

    $commands = $this->app->make(CommandRepository::class)->visible();

    expect($commands)->toHaveCount(2)
        ->and($commands[0]->name)->toBe('Clear Cache')
        ->and($commands[0]->run)->toBe('cache:clear')
        ->and($commands[0]->type)->toBe('warning')
        ->and($commands[1]->variables)->toHaveKey('key')
        ->and($commands[1]->variables['key']->required)->toBeTrue();
});

it('respects ordering and the enabled flag', function () {
    Command::create(['name' => 'Second', 'run' => 'two', 'position' => 20]);
    Command::create(['name' => 'First', 'run' => 'one', 'position' => 10]);
    Command::create(['name' => 'Hidden', 'run' => 'three', 'enabled' => false, 'position' => 5]);

    $commands = $this->app->make(CommandRepository::class)->visible();

    expect($commands)->toHaveCount(2)
        ->and($commands[0]->name)->toBe('First')
        ->and($commands[1]->name)->toBe('Second');
});

it('runs a database-defined command safely through the API', function () {
    Command::create(['name' => 'Migrate', 'run' => 'migrate', 'group' => 'Database']);

    $repository = $this->app->make(CommandRepository::class);
    $id = $repository->visible()[0]->id;

    $this->postJson('_ncr/commands/run', ['command' => $id])
        ->assertOk()
        ->assertJsonPath('execution.status', 'success');

    expect(array_slice($this->executor->arguments, 2))->toBe(['migrate']);
});

it('honours an explicit confirm override from the database', function () {
    Command::create([
        'name' => 'Forced Confirm',
        'run' => 'cache:clear',
        'type' => 'primary',
        'confirm' => true,
    ]);

    Command::create([
        'name' => 'Forced Quiet',
        'run' => 'cache:clear',
        'type' => 'danger',
        'confirm' => false,
    ]);

    $byName = fn (string $name) => collect($this->app->make(CommandRepository::class)->visible())
        ->firstWhere('name', $name);

    expect($byName('Forced Confirm')->requiresConfirmation())->toBeTrue()
        ->and($byName('Forced Quiet')->requiresConfirmation())->toBeFalse()
        ->and($byName('Forced Confirm')->toArray()['needs_confirm'])->toBeTrue()
        ->and($byName('Forced Quiet')->toArray()['needs_confirm'])->toBeFalse();
});

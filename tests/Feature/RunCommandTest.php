<?php

declare(strict_types=1);

use Farsidev\NovaCommandCenter\Actions\ExecuteCommand;
use Farsidev\NovaCommandCenter\Data\CommandDefinition;
use Farsidev\NovaCommandCenter\Data\ExecutionResult;
use Farsidev\NovaCommandCenter\Events\CommandFinished;
use Farsidev\NovaCommandCenter\Events\CommandStarted;
use Farsidev\NovaCommandCenter\Exceptions\CommandNotAllowedException;
use Farsidev\NovaCommandCenter\Support\CommandRepository;
use Farsidev\NovaCommandCenter\Support\History;
use Illuminate\Support\Facades\Event;

function command(string $name): CommandDefinition
{
    foreach (app(CommandRepository::class)->visible() as $command) {
        if ($command->name === $name) {
            return $command;
        }
    }

    throw new RuntimeException("Unknown test command [{$name}].");
}

it('runs an artisan command and records a successful result', function () {
    Event::fake([CommandStarted::class, CommandFinished::class]);

    $result = app(ExecuteCommand::class)->handle(command('Clear Cache'), [], []);

    expect($result->status)->toBe(ExecutionResult::STATUS_SUCCESS)
        ->and($result->exitCode)->toBe(0)
        ->and($this->executor->arguments[1])->toBe(base_path('artisan'))
        ->and(array_slice($this->executor->arguments, 2))->toBe(['cache:clear']);

    Event::assertDispatched(CommandStarted::class);
    Event::assertDispatched(CommandFinished::class);
});

it('stores the execution in history', function () {
    app(ExecuteCommand::class)->handle(command('Clear Cache'), [], []);

    $history = app(History::class)->all();

    expect($history)->toHaveCount(1)
        ->and($history[0]->name)->toBe('Clear Cache');
});

it('passes the execution id to the child process environment', function () {
    $result = app(ExecuteCommand::class)->handle(command('Clear Cache'), [], []);

    expect($this->executor->calls[0]['env'])
        ->toHaveKey('NOVA_COMMAND_CENTER_EXECUTION', $result->id);
});

it('marks a non-zero exit code as failed', function () {
    $this->executor->exitCode = 1;
    $this->executor->output = "boom\n";

    $result = app(ExecuteCommand::class)->handle(command('Clear Cache'), [], []);

    expect($result->status)->toBe(ExecutionResult::STATUS_FAILED)
        ->and($result->exitCode)->toBe(1);
});

it('marks a timed-out command accordingly', function () {
    $this->executor->timedOut = true;

    $result = app(ExecuteCommand::class)->handle(command('Clear Cache'), [], []);

    expect($result->status)->toBe(ExecutionResult::STATUS_TIMED_OUT)
        ->and($result->exitCode)->toBeNull();
});

it('refuses a bash command while bash is disabled', function () {
    app(ExecuteCommand::class)->handle(command('Disk Usage'), [], []);
})->throws(CommandNotAllowedException::class);

it('runs a bash command once bash is enabled', function () {
    config()->set('nova-command-center.bash.enabled', true);

    $result = app(ExecuteCommand::class)->handle(command('Disk Usage'), [], []);

    expect($result->status)->toBe(ExecutionResult::STATUS_SUCCESS)
        ->and($this->executor->arguments)->toBe(['df', '-h']); // bash: no php prefix
});

it('trims output to the configured number of lines', function () {
    $this->executor->output = implode("\n", range(1, 100));

    $command = command('Clear Cache');
    $trimmed = CommandDefinition::fromConfig($command->name, [
        'run' => $command->run,
        'output_size' => 3,
    ]);

    $result = app(ExecuteCommand::class)->handle($trimmed, [], []);

    expect(explode("\n", $result->output))->toHaveCount(3)
        ->and($result->output)->toBe("98\n99\n100");
});

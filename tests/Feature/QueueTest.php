<?php

declare(strict_types=1);

use Farsidev\NovaCommandCenter\Actions\ExecuteCommand;
use Farsidev\NovaCommandCenter\Data\ExecutionResult;
use Farsidev\NovaCommandCenter\Jobs\RunCommandJob;
use Farsidev\NovaCommandCenter\Support\CommandRepository;
use Farsidev\NovaCommandCenter\Support\ExecutionStore;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('nova-command-center.commands', [
        'Queued Job' => ['run' => 'queue:work --once', 'queue' => true],
    ]);
    $this->refreshCommands();
});

it('dispatches a job for a queued command', function () {
    Queue::fake();

    $response = $this->postJson('_ncr/commands/run', ['command' => commandId('Queued Job')]);

    $response->assertAccepted()->assertJsonPath('queued', true);

    Queue::assertPushed(RunCommandJob::class);
});

it('records a pending entry in history the moment a command is queued', function () {
    Queue::fake();

    $response = $this->postJson('_ncr/commands/run', ['command' => commandId('Queued Job')]);
    $executionId = $response->assertAccepted()->json('execution.id');

    // The job never actually runs (Queue::fake), so if history only recorded
    // finished executions this would never appear — proving the operator has
    // a persistent record even when nothing ever consumes the queue.
    $this->getJson('_ncr/history')
        ->assertOk()
        ->assertJsonCount(1, 'history')
        ->assertJsonPath('history.0.id', $executionId)
        ->assertJsonPath('history.0.status', 'pending');
});

it('replaces the pending history entry once the queued command finishes', function () {
    // Default queue connection is "sync" in the test environment, so the job
    // runs inline and the pending record must be replaced, not duplicated.
    $response = $this->postJson('_ncr/commands/run', ['command' => commandId('Queued Job')]);
    $executionId = $response->assertAccepted()->json('execution.id');

    $this->getJson('_ncr/history')
        ->assertOk()
        ->assertJsonCount(1, 'history')
        ->assertJsonPath('history.0.id', $executionId)
        ->assertJsonPath('history.0.status', 'success');
});

it('completes a queued command when the queue runs synchronously', function () {
    // Default queue connection is "sync" in the test environment.
    $response = $this->postJson('_ncr/commands/run', ['command' => commandId('Queued Job')]);

    $executionId = $response->assertAccepted()->json('execution.id');

    $this->getJson("_ncr/executions/{$executionId}")
        ->assertOk()
        ->assertJsonPath('execution.status', 'success');
});

it('runs a queued custom command by carrying its definition with the job', function () {
    config()->set('nova-command-center.custom_commands', ['artisan']);
    $this->refreshCommands();

    $response = $this->postJson('_ncr/commands/run', [
        'custom' => ['type' => 'artisan', 'run' => 'inspire'],
        'mode' => 'queue',
    ]);

    $executionId = $response->assertAccepted()->json('execution.id');

    // The sync queue driver runs the job inline; the execution must complete
    // even though the custom command's id never exists in the repository.
    $this->getJson("_ncr/executions/{$executionId}")
        ->assertOk()
        ->assertJsonPath('execution.status', 'success');

    expect(array_slice($this->executor->arguments, 2))->toBe(['inspire']);
});

it('marks the execution failed when the command vanishes before the job runs', function () {
    $store = app(ExecutionStore::class);

    $store->put(new ExecutionResult(
        id: 'exec-vanished',
        commandId: 'gone',
        name: 'Gone',
        display: 'gone',
        status: ExecutionResult::STATUS_PENDING,
        exitCode: null,
        output: '',
        startedAt: now()->toIso8601String(),
    ));

    $job = new RunCommandJob('gone', [], [], 'exec-vanished');
    $job->handle(
        app(CommandRepository::class),
        app(ExecuteCommand::class),
    );

    expect($store->get('exec-vanished')?->status)->toBe('failed');
});

it('marks the execution failed when the job itself fails', function () {
    $store = app(ExecutionStore::class);

    $store->put(new ExecutionResult(
        id: 'exec-crashed',
        commandId: 'x',
        name: 'X',
        display: 'x',
        status: ExecutionResult::STATUS_RUNNING,
        exitCode: null,
        output: 'partial output',
        startedAt: now()->toIso8601String(),
    ));

    (new RunCommandJob('x', [], [], 'exec-crashed'))->failed(new RuntimeException('worker died'));

    $result = $store->get('exec-crashed');

    expect($result?->status)->toBe('failed')
        ->and($result?->output)->toContain('partial output')
        ->and($result?->output)->toContain('worker died');
});

it('does not overwrite a finished execution from failed()', function () {
    $store = app(ExecutionStore::class);

    $store->put(new ExecutionResult(
        id: 'exec-done',
        commandId: 'x',
        name: 'X',
        display: 'x',
        status: ExecutionResult::STATUS_SUCCESS,
        exitCode: 0,
        output: 'ok',
        startedAt: now()->toIso8601String(),
    ));

    (new RunCommandJob('x', [], [], 'exec-done'))->failed(new RuntimeException('late failure'));

    expect($store->get('exec-done')?->status)->toBe('success');
});

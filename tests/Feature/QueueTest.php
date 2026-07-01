<?php

declare(strict_types=1);

use Farsidev\NovaCommandCenter\Jobs\RunCommandJob;
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

it('completes a queued command when the queue runs synchronously', function () {
    // Default queue connection is "sync" in the test environment.
    $response = $this->postJson('_ncr/commands/run', ['command' => commandId('Queued Job')]);

    $executionId = $response->assertAccepted()->json('execution.id');

    $this->getJson("_ncr/executions/{$executionId}")
        ->assertOk()
        ->assertJsonPath('execution.status', 'success');
});

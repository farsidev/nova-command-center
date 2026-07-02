<?php

declare(strict_types=1);

use Farsi\NovaCommandCenter\Actions\ExecuteCommand;
use Farsi\NovaCommandCenter\Exceptions\CommandNotAllowedException;

it('prevents overlapping execution of a locked command', function () {
    config()->set('nova-command-center.without_overlapping.commands', ['Clear Cache']);

    $command = command('Clear Cache');

    // Hold the lock the guard will try to acquire.
    $store = app('cache')->store('array')->getStore();
    $lock = $store->lock('nova-command-center:lock:command:'.$command->id, 30);
    $lock->get();

    app(ExecuteCommand::class)->handle($command, [], []);
})->throws(CommandNotAllowedException::class);

it('allows execution when no lock is held', function () {
    config()->set('nova-command-center.without_overlapping.commands', ['Clear Cache']);

    $result = app(ExecuteCommand::class)->handle(command('Clear Cache'), [], []);

    expect($result->successful())->toBeTrue();
});

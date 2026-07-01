<?php

declare(strict_types=1);

use Farsidev\NovaCommandCenter\Exceptions\CommandNotAllowedException;
use Farsidev\NovaCommandCenter\Support\CommandRepository;

function repository(array $config = []): CommandRepository
{
    return new CommandRepository(array_merge([
        'history' => 5,
        'bash' => ['enabled' => false],
        'custom_commands' => [],
        'commands' => [
            'Clear Cache' => ['run' => 'cache:clear', 'group' => 'Cache'],
            'Broken' => ['group' => 'Nope'], // no run — must be skipped
        ],
    ], $config));
}

it('loads only commands that define a run string', function () {
    $commands = repository()->visible();

    expect($commands)->toHaveCount(1)
        ->and($commands[0]->name)->toBe('Clear Cache');
});

it('applies safe defaults for missing keys', function () {
    $command = repository()->visible()[0];

    expect($command->type)->toBe('primary')
        ->and($command->commandType)->toBe('artisan')
        ->and($command->group)->toBe('Cache')
        ->and($command->timeout)->toBeInt()
        ->and($command->help)->toBeNull();
});

it('never returns null for string properties (issue #36)', function () {
    $command = (new CommandRepository([
        'commands' => ['X' => ['run' => 'x', 'help' => null, 'type' => null, 'group' => null]],
    ]))->visible()[0];

    expect($command->type)->toBeString()
        ->and($command->group)->toBeString()
        ->and($command->run)->toBe('x');
});

it('finds a command by its generated id', function () {
    $repository = repository();
    $id = $repository->visible()[0]->id;

    expect($repository->find($id)?->name)->toBe('Clear Cache')
        ->and($repository->find('missing'))->toBeNull();
});

it('rejects custom commands whose type is not allow-listed', function () {
    repository(['custom_commands' => []])->makeCustom('artisan', 'migrate');
})->throws(CommandNotAllowedException::class);

it('permits custom commands of an allow-listed type', function () {
    $command = repository(['custom_commands' => ['artisan']])->makeCustom('artisan', 'migrate');

    expect($command->run)->toBe('migrate')
        ->and($command->commandType)->toBe('artisan');
});

it('reports whether bash is enabled', function () {
    expect(repository(['bash' => ['enabled' => false]])->bashEnabled())->toBeFalse()
        ->and(repository(['bash' => ['enabled' => true]])->bashEnabled())->toBeTrue();
});

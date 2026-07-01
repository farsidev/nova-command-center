<?php

declare(strict_types=1);

use Farsidev\NovaCommandCenter\Contracts\CommandSource;
use Farsidev\NovaCommandCenter\Support\CommandRepository;
use Farsidev\NovaCommandCenter\Support\Sources\ConfigCommandSource;

it('reads definitions from the config array', function () {
    $source = new ConfigCommandSource([
        'commands' => [
            'Clear Cache' => ['run' => 'cache:clear'],
        ],
    ]);

    expect(iterator_to_array((function () use ($source) {
        yield from $source->definitions();
    })()))->toBe(['Clear Cache' => ['run' => 'cache:clear']]);
});

it('returns nothing when the config has no commands', function () {
    expect((new ConfigCommandSource([]))->definitions())->toBe([]);
});

it('defaults the repository to the config source', function () {
    $repository = new CommandRepository([
        'commands' => ['Clear Cache' => ['run' => 'cache:clear']],
    ]);

    expect($repository->visible())->toHaveCount(1)
        ->and($repository->visible()[0]->name)->toBe('Clear Cache');
});

it('loads commands from any injected source, ignoring the config commands', function () {
    $source = new class implements CommandSource
    {
        public function definitions(): iterable
        {
            return [
                'From Source' => ['run' => 'queue:work', 'group' => 'Queue'],
            ];
        }
    };

    // Config still carries the security switches, but its "commands" are unused.
    $repository = new CommandRepository([
        'bash' => ['enabled' => false],
        'commands' => ['Ignored' => ['run' => 'cache:clear']],
    ], $source);

    expect($repository->visible())->toHaveCount(1)
        ->and($repository->visible()[0]->name)->toBe('From Source')
        ->and($repository->visible()[0]->group)->toBe('Queue')
        ->and($repository->bashEnabled())->toBeFalse();
});

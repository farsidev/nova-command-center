<?php

declare(strict_types=1);

use Farsi\NovaCommandCenter\Data\CommandDefinition;

it('parses variables stored in the Nova Repeater JSON shape', function () {
    $command = CommandDefinition::fromConfig('Add Flights', [
        'run' => 'club:add-flights --club={club} --region={region}',
        'variables' => [
            [
                'type' => 'variable',
                'fields' => [
                    'name' => 'club',
                    'label' => 'Club',
                    'type' => 'model',
                    'required' => true,
                    'model' => 'App\\Models\\Club',
                    'value_column' => 'id',
                    'label_column' => 'name',
                    'search_columns' => 'name, slug',
                ],
            ],
            [
                'type' => 'variable',
                'fields' => [
                    'name' => 'region',
                    'label' => 'Region',
                    'type' => 'select',
                    'options' => "DK:Denmark\nNO:Norway\nSE",
                    'rules' => 'string|max:2',
                ],
            ],
        ],
    ]);

    expect($command->variables)->toHaveKeys(['club', 'region']);

    $club = $command->variables['club'];
    expect($club->type)->toBe('model')
        ->and($club->model)->toBe('App\\Models\\Club')
        ->and($club->valueColumn)->toBe('id')
        ->and($club->labelColumn)->toBe('name')
        ->and($club->searchColumns)->toBe(['name', 'slug']);

    $region = $command->variables['region'];
    expect($region->type)->toBe('select')
        ->and($region->options)->toBe([
            ['value' => 'DK', 'label' => 'Denmark'],
            ['value' => 'NO', 'label' => 'Norway'],
            ['value' => 'SE', 'label' => 'SE'],
        ])
        ->and($region->rules)->toBe(['string', 'max:2']);
});

it('parses flags stored in the Nova Repeater JSON shape', function () {
    $command = CommandDefinition::fromConfig('Migrate', [
        'run' => 'migrate',
        'flags' => [
            [
                'type' => 'flag',
                'fields' => [
                    'label' => 'Force',
                    'flag' => '--force',
                    'default' => true,
                    'help' => 'Skip the confirmation prompt.',
                ],
            ],
        ],
    ]);

    expect($command->flags)->toHaveCount(1)
        ->and($command->flags[0]->flag)->toBe('--force')
        ->and($command->flags[0]->label)->toBe('Force')
        ->and($command->flags[0]->default)->toBeTrue()
        ->and($command->flags[0]->help)->toBe('Skip the confirmation prompt.');
});

it('parses a plain list of variable objects carrying their own name', function () {
    $command = CommandDefinition::fromConfig('Forget Key', [
        'run' => 'cache:forget {key}',
        'variables' => [
            ['name' => 'key', 'label' => 'Cache key', 'required' => true],
        ],
    ]);

    expect($command->variables)->toHaveKey('key')
        ->and($command->variables['key']->label)->toBe('Cache key');
});

it('skips list-shaped variable entries without a usable name', function () {
    $command = CommandDefinition::fromConfig('X', [
        'run' => 'x {a}',
        'variables' => [
            ['label' => 'No name here'],
            ['type' => 'variable', 'fields' => ['label' => 'Still no name']],
            ['name' => 'a'],
        ],
    ]);

    expect($command->variables)->toHaveCount(1)->toHaveKey('a');
});

it('keeps supporting the classic keyed map shape untouched', function () {
    $command = CommandDefinition::fromConfig('Forget Key', [
        'run' => 'cache:forget {key}',
        'variables' => [
            'key' => ['label' => 'Cache key', 'type' => 'text'],
        ],
    ]);

    expect($command->variables)->toHaveKey('key')
        ->and($command->variables['key']->label)->toBe('Cache key');
});

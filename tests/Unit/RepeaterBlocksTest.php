<?php

declare(strict_types=1);

use Farsi\NovaCommandCenter\Support\RepeaterBlocks;

it('converts a config-style variables map into repeater blocks', function () {
    $blocks = (RepeaterBlocks::variables())([
        'club' => [
            'label' => 'Club',
            'type' => 'model',
            'model' => 'App\\Models\\Club',
            'search_columns' => ['name', 'slug'],
        ],
        'region' => [
            'label' => 'Region',
            'type' => 'select',
            'options' => [
                ['value' => 'DK', 'label' => 'Denmark'],
                ['value' => 'SE', 'label' => 'SE'],
            ],
            'rules' => ['string', 'max:2'],
        ],
    ]);

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['type'])->toBe('variable')
        ->and($blocks[0]['fields']['name'])->toBe('club')
        ->and($blocks[0]['fields']['search_columns'])->toBe('name,slug')
        ->and($blocks[1]['fields']['name'])->toBe('region')
        ->and($blocks[1]['fields']['options'])->toBe("DK:Denmark\nSE")
        ->and($blocks[1]['fields']['rules'])->toBe('string|max:2');
});

it('passes repeater-shaped variables through untouched', function () {
    $blocks = (RepeaterBlocks::variables())([
        ['type' => 'variable', 'fields' => ['name' => 'key', 'label' => 'Key', 'options' => "a:A\nb"]],
    ]);

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['fields']['name'])->toBe('key')
        ->and($blocks[0]['fields']['options'])->toBe("a:A\nb");
});

it('converts label shorthand and option maps', function () {
    $blocks = (RepeaterBlocks::variables())([
        'key' => 'Cache key',
        'mode' => ['options' => ['on' => 'Enabled', 'off' => 'Disabled']],
    ]);

    expect($blocks[0]['fields'])->toMatchArray(['name' => 'key', 'label' => 'Cache key'])
        ->and($blocks[1]['fields']['options'])->toBe("on:Enabled\noff:Disabled");
});

it('converts every flag shape into repeater blocks', function () {
    $blocks = (RepeaterBlocks::flags())([
        ['label' => 'Force', 'flag' => '--force', 'default' => true],
        '--seed' => 'Seed the database',
        'Verbose' => '-v',
        ['type' => 'flag', 'fields' => ['label' => 'Quiet', 'flag' => '--quiet', 'default' => false]],
    ]);

    expect($blocks)->toHaveCount(4)
        ->and($blocks[0]['fields']['flag'])->toBe('--force')
        ->and($blocks[1]['fields'])->toMatchArray(['label' => 'Seed the database', 'flag' => '--seed'])
        ->and($blocks[2]['fields'])->toMatchArray(['label' => 'Verbose', 'flag' => '-v'])
        ->and($blocks[3]['fields']['flag'])->toBe('--quiet');
});

it('backfills the DTO defaults for required and type', function () {
    $blocks = (RepeaterBlocks::variables())([
        'region' => ['label' => 'Region'],
        'tag' => ['label' => 'Tag', 'required' => false, 'type' => 'select'],
    ]);

    expect($blocks[0]['fields'])->toMatchArray(['required' => true, 'type' => 'text'])
        ->and($blocks[1]['fields'])->toMatchArray(['required' => false, 'type' => 'select']);
});

it('returns an empty list for null or junk input', function () {
    expect((RepeaterBlocks::variables())(null))->toBe([])
        ->and((RepeaterBlocks::variables())('junk'))->toBe([])
        ->and((RepeaterBlocks::flags())(null))->toBe([]);
});

<?php

declare(strict_types=1);

use Farsidev\NovaCommandCenter\Tests\Fakes\TestClub;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('clubs', function ($table): void {
        $table->id();
        $table->string('name');
        $table->string('secret')->nullable();
    });

    TestClub::create(['id' => 1, 'name' => 'Chelsea FC', 'secret' => 'sensitive-1']);
    TestClub::create(['id' => 2, 'name' => 'Arsenal FC', 'secret' => 'sensitive-2']);
    TestClub::create(['id' => 3, 'name' => 'FC Copenhagen', 'secret' => 'sensitive-3']);

    config()->set('nova-command-center.searchable_models', [TestClub::class]);
    config()->set('nova-command-center.commands', [
        'Add Flight Services' => [
            'run' => 'club:add-flights --club={club}',
            'group' => 'Club',
            'variables' => [
                'club' => [
                    'label' => 'Club',
                    'type' => 'model',
                    'model' => TestClub::class,
                ],
            ],
        ],
        'Unlisted Model' => [
            'run' => 'club:touch --club={club}',
            'group' => 'Club',
            'variables' => [
                'club' => [
                    'label' => 'Club',
                    'type' => 'model',
                    'model' => 'Not\\A\\Real\\Class',
                ],
            ],
        ],
        'Guarded Search' => [
            'run' => 'club:touch --club={club}',
            'can' => 'ncr-guarded-search',
            'variables' => [
                'club' => [
                    'label' => 'Club',
                    'type' => 'model',
                    'model' => TestClub::class,
                ],
            ],
        ],
    ]);
    $this->refreshCommands();
});

afterEach(function () {
    Schema::dropIfExists('clubs');
});

it('searches the allow-listed model and returns only value/label', function () {
    $id = commandId('Add Flight Services');

    $response = $this->getJson("_ncr/commands/{$id}/variables/club/search?q=chelsea");

    $response->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.value', '1')
        ->assertJsonPath('results.0.label', 'Chelsea FC');

    expect($response->json('results.0'))->not->toHaveKey('secret');
});

it('searches case-insensitively', function () {
    $id = commandId('Add Flight Services');

    $response = $this->getJson("_ncr/commands/{$id}/variables/club/search?q=CHELSEA");

    $response->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.label', 'Chelsea FC');
});

it('returns results for an empty search term, capped at the page size', function () {
    $id = commandId('Add Flight Services');

    $response = $this->getJson("_ncr/commands/{$id}/variables/club/search");

    $response->assertOk()->assertJsonCount(3, 'results');
});

it('refuses to search a model that is not allow-listed', function () {
    $id = commandId('Unlisted Model');

    $this->getJson("_ncr/commands/{$id}/variables/club/search?q=chelsea")->assertForbidden();
});

it('returns 404 for an unknown command', function () {
    $this->getJson('_ncr/commands/does-not-exist/variables/club/search?q=x')->assertNotFound();
});

it('returns 404 for a variable that is not searchable', function () {
    $id = commandId('Add Flight Services');

    $this->getJson("_ncr/commands/{$id}/variables/does-not-exist/search?q=x")->assertNotFound();
});

it('respects the per-command gate on the search endpoint', function () {
    Gate::define('ncr-guarded-search', fn ($user = null) => false);

    $id = commandId('Guarded Search');

    $this->getJson("_ncr/commands/{$id}/variables/club/search?q=x")->assertForbidden();
});

it('rejects a run submission with a non-existent id when the model is allow-listed', function () {
    $id = commandId('Add Flight Services');

    $this->postJson('_ncr/commands/run', [
        'command' => $id,
        'variables' => ['club' => '999'],
    ])->assertUnprocessable()->assertJsonValidationErrors('variables.club');
});

it('accepts a run submission with a real id', function () {
    $id = commandId('Add Flight Services');

    $this->postJson('_ncr/commands/run', [
        'command' => $id,
        'variables' => ['club' => '2'],
    ])->assertOk();

    expect(array_slice($this->executor->arguments, 2))->toBe(['club:add-flights', '--club=2']);
});

it('does not enforce an exists check when the model is not allow-listed', function () {
    $id = commandId('Unlisted Model');

    $this->postJson('_ncr/commands/run', [
        'command' => $id,
        'variables' => ['club' => 'anything'],
    ])->assertOk();
});

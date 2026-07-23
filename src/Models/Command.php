<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Models;

use Farsi\NovaCommandCenter\Data\CommandDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model backing the optional "database" command source.
 *
 * The table is NOT created automatically. Publish and run the migration only if
 * you opt into managing commands in the database:
 *
 *     php artisan vendor:publish --tag=nova-command-center-migrations
 *     php artisan migrate
 *
 * A row maps one-to-one onto a config command definition; {@see toDefinition()}
 * produces exactly the array shape {@see CommandDefinition::fromConfig()}
 * expects, so the same coercion and security guarantees apply.
 *
 * @property string $name
 * @property string $run
 * @property string|null $command_type
 * @property string|null $group
 * @property string|null $type
 * @property string|null $help
 * @property int|null $timeout
 * @property int|null $output_size
 * @property array<string, mixed>|bool|null $queue
 * @property string|null $can
 * @property bool|null $confirm
 * @property array<int|string, mixed>|null $variables
 * @property array<int|string, mixed>|null $flags
 * @property bool $enabled
 * @property int $position
 */
class Command extends Model
{
    protected $table = 'nova_command_center_commands';

    /**
     * Explicit allow-list of mass-assignable columns. Every field is an operator
     * setting rather than sensitive account state, but listing them keeps the
     * model closed by default instead of relying on `$guarded = []`.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'run',
        'command_type',
        'group',
        'type',
        'help',
        'timeout',
        'output_size',
        'queue',
        'can',
        'confirm',
        'variables',
        'flags',
        'enabled',
        'position',
    ];

    /**
     * The `$casts` property (rather than the Laravel 11+ `casts()` method) keeps
     * the model compatible with Laravel 10.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'timeout' => 'integer',
        'output_size' => 'integer',
        'queue' => 'array',
        'confirm' => 'boolean',
        'variables' => 'array',
        'flags' => 'array',
        'enabled' => 'boolean',
        'position' => 'integer',
    ];

    /**
     * Convert the row into the raw definition array consumed by the repository.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(): array
    {
        return array_filter([
            'run' => $this->run,
            'command_type' => $this->command_type,
            'group' => $this->group,
            'type' => $this->type,
            'help' => $this->help,
            'timeout' => $this->timeout,
            'output_size' => $this->output_size,
            'queue' => $this->queue,
            'can' => $this->can,
            'confirm' => $this->confirm,
            'variables' => $this->variables,
            'flags' => $this->flags,
        ], static fn ($value): bool => $value !== null);
    }
}

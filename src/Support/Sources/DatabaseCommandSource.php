<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Support\Sources;

use Farsi\NovaCommandCenter\Contracts\CommandSource;
use Farsi\NovaCommandCenter\Models\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * An optional source that reads command definitions from an Eloquent model,
 * allowing commands to be managed through a Nova resource instead of the config
 * file.
 *
 * SECURITY: enabling this driver moves the allow-list out of version control and
 * into the database, so anyone who can edit the backing rows can define what runs.
 * Gate the resource tightly (super-admins only) and prefer artisan over bash.
 * See the "Managing commands in the database" section of the README.
 *
 * @template TModel of Model
 */
final class DatabaseCommandSource implements CommandSource
{
    /**
     * @param  class-string<TModel>  $model
     */
    public function __construct(private readonly string $model = Command::class) {}

    public function definitions(): iterable
    {
        $model = $this->model;

        $query = $model::query();

        // Only surface rows the store considers active, newest configuration first.
        if ($this->hasColumn('enabled')) {
            $query->where('enabled', true);
        }

        if ($this->hasColumn('position')) {
            $query->orderBy('position');
        }

        $definitions = [];

        foreach ($query->get() as $row) {
            /** @var Model $row */
            $name = $row->getAttribute('name');

            if (!is_string($name) || $name === '') {
                continue;
            }

            $definitions[$name] = $row instanceof Command
                ? $row->toDefinition()
                : $row->toArray();
        }

        return $definitions;
    }

    private function hasColumn(string $column): bool
    {
        $model = $this->model;

        return (new $model)->getConnection()
            ->getSchemaBuilder()
            ->hasColumn((new $model)->getTable(), $column);
    }
}

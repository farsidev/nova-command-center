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
     * @var array<string, bool>
     */
    private array $columnCache = [];

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

    /**
     * Memoized per instance (the source is bound as a singleton, so this is
     * effectively once per request): the schema builder call behind this is a
     * real query (`information_schema`/`DESCRIBE`), and {@see self::definitions()}
     * checks two columns, so an unmemoized version instantiates the model and
     * queries the schema up to four times for something that never changes
     * mid-request. Deliberately request-scoped rather than cached in the
     * configured cache store — a store-level cache would need a TTL, and a
     * migration adding "position"/"enabled" wouldn't take effect until that
     * TTL expired.
     */
    private function hasColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columnCache)) {
            return $this->columnCache[$column];
        }

        $model = $this->model;
        $instance = new $model;

        return $this->columnCache[$column] = $instance->getConnection()
            ->getSchemaBuilder()
            ->hasColumn($instance->getTable(), $column);
    }
}

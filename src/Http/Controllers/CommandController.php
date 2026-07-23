<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Http\Controllers;

use Farsi\NovaCommandCenter\Actions\ExecuteCommand;
use Farsi\NovaCommandCenter\Data\ExecutionResult;
use Farsi\NovaCommandCenter\Exceptions\CommandNotAllowedException;
use Farsi\NovaCommandCenter\Http\Requests\RunCommandRequest;
use Farsi\NovaCommandCenter\Jobs\RunCommandJob;
use Farsi\NovaCommandCenter\Support\Cast;
use Farsi\NovaCommandCenter\Support\CommandBuilder;
use Farsi\NovaCommandCenter\Support\CommandRepository;
use Farsi\NovaCommandCenter\Support\ExecutionStore;
use Farsi\NovaCommandCenter\Support\History;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class CommandController extends Controller
{
    public function __construct(
        private readonly CommandRepository $commands,
        private readonly History $history,
        private readonly ExecutionStore $store,
        private readonly CommandBuilder $builder,
        private readonly Config $config,
    ) {}

    public function index(): JsonResponse
    {
        return new JsonResponse([
            'commands' => array_map(
                static fn ($command): array => $command->toArray(),
                $this->commands->visible(),
            ),
            'history' => array_map(
                static fn (ExecutionResult $result): array => $result->toArray(),
                $this->history->all(),
            ),
            'config' => [
                'help' => $this->config->get('nova-command-center.help'),
                'navigation_label' => $this->config->get('nova-command-center.navigation_label'),
                'custom_commands' => $this->commands->allowedCustomTypes(),
                'bash_enabled' => $this->commands->bashEnabled(),
                'history_enabled' => $this->commands->historySize() > 0,
            ],
        ]);
    }

    public function run(RunCommandRequest $request, ExecuteCommand $runner): JsonResponse
    {
        $command = $request->command($this->commands);

        if ($command === null) {
            return new JsonResponse(['message' => 'The selected command does not exist.'], 422);
        }

        if ($response = $this->enforceRateLimit($request)) {
            return $response;
        }

        $values = $request->resolvedValues();
        $flags = $request->resolvedFlags();
        $ranBy = $this->ranBy($request);

        try {
            if ($request->queued()) {
                $executionId = (string) Str::uuid();
                $built = $this->builder->build($command, $values, $flags);

                $pending = new ExecutionResult(
                    id: $executionId,
                    commandId: $command->id,
                    name: $command->name,
                    display: $built->display,
                    status: ExecutionResult::STATUS_PENDING,
                    exitCode: null,
                    output: '',
                    startedAt: now()->toIso8601String(),
                    ranBy: $ranBy,
                    variables: $values,
                    flags: $flags,
                );

                $this->store->put($pending);

                // Record it in history immediately, not just the live-poll
                // store. Otherwise a queued command that is slow to start (or
                // never starts, e.g. no worker is consuming the queue) leaves
                // no trace anywhere once the operator navigates away or
                // reloads — the job that eventually finishes replaces this
                // same entry in place (History::push dedupes by id).
                $this->history->push($pending);

                $job = new RunCommandJob(
                    $command->id,
                    $values,
                    $flags,
                    $executionId,
                    $ranBy,
                    $command->timeout,
                    $request->customPayload(),
                );

                if ($command->queue !== null && $command->queue['connection'] !== null) {
                    $job->onConnection($command->queue['connection']);
                }

                if ($command->queue !== null && $command->queue['queue'] !== null) {
                    $job->onQueue($command->queue['queue']);
                }

                dispatch($job);

                return new JsonResponse([
                    'queued' => true,
                    'execution' => $this->store->get($executionId)?->toArray(),
                ], 202);
            }

            $result = $runner->handle($command, $values, $flags, ranBy: $ranBy);

            return new JsonResponse([
                'queued' => false,
                'execution' => $result->toArray(),
            ]);
        } catch (CommandNotAllowedException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }
    }

    /**
     * Type-ahead search backing a "model" variable. Only selects the two
     * configured columns (never the whole row) and only ever queries a
     * model class explicitly allow-listed in "searchable_models", so this
     * endpoint cannot be used to read arbitrary application data.
     */
    public function search(Request $request, string $command, string $variable): JsonResponse
    {
        $definition = $this->commands->find($command);

        if ($definition === null) {
            return new JsonResponse(['message' => 'The selected command does not exist.'], 404);
        }

        if ($definition->can !== null && !Gate::allows($definition->can, $definition)) {
            abort(403);
        }

        $target = null;

        foreach ($definition->variables as $candidate) {
            if ($candidate->name === $variable) {
                $target = $candidate;

                break;
            }
        }

        if ($target === null || $target->type !== 'model' || $target->model === null) {
            return new JsonResponse(['message' => 'This variable is not searchable.'], 404);
        }

        if (!in_array($target->model, $this->commands->searchableModels(), true)) {
            return new JsonResponse(['message' => 'This model is not allow-listed for search.'], 403);
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $target->model;
        $valueColumn = $target->valueColumn;
        $labelColumn = $target->labelColumn;
        $searchColumns = $target->searchColumns;

        $query = $modelClass::query();
        $value = Cast::string($request->query('value'), '');

        if ($value !== '') {
            // Resolve one already-known value to its label — used by the run
            // modal to show a friendly label for a variable's pre-filled
            // default instead of the raw stored value — rather than a
            // free-text search.
            $query->where($valueColumn, $value);
            $limit = 1;
        } else {
            $term = Cast::string($request->query('q'), '');

            if ($term !== '') {
                // LIKE is case-sensitive on some drivers (e.g. PostgreSQL) and not
                // others (MySQL's default collation, SQLite), so both sides are
                // explicitly lower-cased with the portable LOWER() SQL function
                // instead of relying on driver-specific behaviour (or ILIKE,
                // which isn't available everywhere). The column is cast to a
                // character type first: LOWER() rejects non-text column types
                // outright on strict drivers (e.g. Postgres jsonb, as used by
                // some translatable-column packages), where a plain LIKE would
                // otherwise still work via an implicit cast.
                $escaped = mb_strtolower(addcslashes($term, '%_\\'));
                $castType = (new $modelClass)->getConnection()->getDriverName() === 'mysql' ? 'CHAR' : 'TEXT';

                $query->where(function (Builder $builder) use ($searchColumns, $escaped, $castType): void {
                    foreach ($searchColumns as $column) {
                        $builder->orWhere(DB::raw('LOWER(CAST('.$column.' AS '.$castType.'))'), 'like', '%'.$escaped.'%');
                    }
                });
            }

            $limit = 20;
        }

        $results = $query
            ->limit($limit)
            ->get([$valueColumn, $labelColumn])
            ->map(static fn (Model $record): array => [
                'value' => Cast::string($record->getAttribute($valueColumn)),
                'label' => Cast::string($record->getAttribute($labelColumn)),
            ])
            ->values();

        return new JsonResponse(['results' => $results]);
    }

    private function enforceRateLimit(Request $request): ?JsonResponse
    {
        $limit = $this->config->get('nova-command-center.rate_limit');

        if (!is_int($limit) || $limit <= 0) {
            return null;
        }

        $identifier = $this->user($request)?->getAuthIdentifier() ?? $request->ip();
        $key = 'nova-command-center:'.Cast::string($identifier, 'guest');

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return new JsonResponse([
                'message' => 'Too many commands. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ], 429);
        }

        RateLimiter::hit($key, 60);

        return null;
    }

    private function ranBy(Request $request): ?string
    {
        $user = $this->user($request);

        if ($user === null) {
            return null;
        }

        foreach (['name', 'email'] as $attribute) {
            if (isset($user->{$attribute}) && is_string($user->{$attribute})) {
                return $user->{$attribute};
            }
        }

        return Cast::nullableString($user->getAuthIdentifier());
    }

    /**
     * The acting operator. Resolved through Nova's configured guard when the
     * host app sets one — a panel authenticated on a non-default guard (e.g.
     * "admin") otherwise reports no user here, so history rows lose their
     * "ran by" attribution and rate limiting falls back to keying on IP.
     */
    private function user(Request $request): ?Authenticatable
    {
        $guard = $this->config->get('nova.guard');

        return $request->user(is_string($guard) ? $guard : null);
    }
}

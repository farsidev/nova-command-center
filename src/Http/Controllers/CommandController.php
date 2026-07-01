<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Http\Controllers;

use Farsidev\NovaCommandCenter\Actions\ExecuteCommand;
use Farsidev\NovaCommandCenter\Data\ExecutionResult;
use Farsidev\NovaCommandCenter\Exceptions\CommandNotAllowedException;
use Farsidev\NovaCommandCenter\Http\Requests\RunCommandRequest;
use Farsidev\NovaCommandCenter\Jobs\RunCommandJob;
use Farsidev\NovaCommandCenter\Support\Cast;
use Farsidev\NovaCommandCenter\Support\CommandRepository;
use Farsidev\NovaCommandCenter\Support\ExecutionStore;
use Farsidev\NovaCommandCenter\Support\History;
use Illuminate\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class CommandController extends Controller
{
    public function __construct(
        private readonly CommandRepository $commands,
        private readonly History $history,
        private readonly ExecutionStore $store,
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
        $command = $request->command();

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

                $this->store->put(new ExecutionResult(
                    id: $executionId,
                    commandId: $command->id,
                    name: $command->name,
                    display: $command->run,
                    status: ExecutionResult::STATUS_PENDING,
                    exitCode: null,
                    output: '',
                    startedAt: now()->toIso8601String(),
                    ranBy: $ranBy,
                ));

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

    private function enforceRateLimit(Request $request): ?JsonResponse
    {
        $limit = $this->config->get('nova-command-center.rate_limit');

        if (!is_int($limit) || $limit <= 0) {
            return null;
        }

        $identifier = $request->user()?->getAuthIdentifier() ?? $request->ip();
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
        $user = $request->user();

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
}

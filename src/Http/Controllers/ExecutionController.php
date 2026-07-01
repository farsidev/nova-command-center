<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Http\Controllers;

use Farsidev\NovaCommandCenter\Support\ExecutionStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class ExecutionController extends Controller
{
    public function __construct(private readonly ExecutionStore $store) {}

    /**
     * Poll the live state (and progress) of an execution.
     */
    public function show(string $execution): JsonResponse
    {
        $result = $this->store->get($execution);

        if ($result === null) {
            return new JsonResponse(['message' => 'Execution not found.'], 404);
        }

        return new JsonResponse([
            'execution' => $result->toArray(),
            'progress' => $this->store->getProgress($execution),
        ]);
    }
}

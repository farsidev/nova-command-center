<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Http\Controllers;

use Farsi\NovaCommandCenter\Data\ExecutionResult;
use Farsi\NovaCommandCenter\Support\History;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class HistoryController extends Controller
{
    public function __construct(private readonly History $history) {}

    public function index(): JsonResponse
    {
        return new JsonResponse([
            'history' => array_map(
                static fn (ExecutionResult $result): array => $result->toArray(),
                $this->history->all(),
            ),
        ]);
    }

    public function destroy(): JsonResponse
    {
        $this->history->clear();

        return new JsonResponse(['history' => []]);
    }
}

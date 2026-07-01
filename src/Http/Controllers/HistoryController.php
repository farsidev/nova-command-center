<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Http\Controllers;

use Farsidev\NovaCommandCenter\Data\ExecutionResult;
use Farsidev\NovaCommandCenter\Support\History;
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

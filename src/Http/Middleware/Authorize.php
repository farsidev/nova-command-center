<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Http\Middleware;

use Closure;
use Farsi\NovaCommandCenter\CommandCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Nova;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every Command Center endpoint.
 *
 * Authorization is layered:
 *   1. The tool must be visible to the user (Nova canSee / authorizedToSee).
 *   2. If a "runCommand" gate ability is defined, it must pass.
 */
final class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        $tool = collect(Nova::registeredTools())
            ->first(static fn ($tool): bool => $tool instanceof CommandCenter);

        if ($tool instanceof CommandCenter && !$tool->authorize($request)) {
            abort(403);
        }

        $ability = config('nova-command-center.authorize');

        if (is_string($ability) && $ability !== '' && Gate::has($ability) && Gate::denies($ability)) {
            abort(403);
        }

        return $next($request);
    }
}

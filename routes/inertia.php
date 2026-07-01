<?php

declare(strict_types=1);

use Laravel\Nova\Http\Requests\NovaRequest;

/*
|--------------------------------------------------------------------------
| Tool (Inertia) Route
|--------------------------------------------------------------------------
|
| This route renders the Command Center page. It is registered within a group
| prefixed with the tool name and guarded by Nova's middleware, so the menu
| link resolves to "/nova-command-center".
|
*/

Route::get('/', fn (NovaRequest $request) => inertia('NovaCommandCenter'));

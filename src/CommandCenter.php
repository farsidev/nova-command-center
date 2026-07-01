<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter;

use Illuminate\Http\Request;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;

class CommandCenter extends Tool
{
    /**
     * Register any scripts/styles the tool needs. Called by Nova while serving.
     */
    public function boot(): void
    {
        Nova::script('nova-command-center', __DIR__.'/../dist/js/tool.js');
        Nova::style('nova-command-center', __DIR__.'/../dist/css/tool.css');
    }

    /**
     * Build the tool's sidebar menu entry.
     */
    public function menu(Request $request): MenuSection
    {
        $label = config('nova-command-center.navigation_label', 'Command Center');

        return MenuSection::make(is_string($label) ? $label : 'Command Center')
            ->path('/nova-command-center')
            ->icon('server');
    }
}

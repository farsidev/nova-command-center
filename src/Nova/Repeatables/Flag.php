<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Nova\Repeatables;

use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Repeater\Repeatable;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * A structured editor block for one optional command flag, used by the bundled
 * Command resource's Repeater field instead of hand-written JSON.
 */
class Flag extends Repeatable
{
    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Label', 'label')
                ->rules('required', 'string', 'max:255'),

            Text::make('Flag', 'flag')
                ->rules('required', 'string', 'max:100')
                ->help('The literal token appended when checked, e.g. "--force".'),

            Boolean::make('Checked by default', 'default')->default(false),

            Text::make('Help', 'help')->rules('nullable', 'string'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Nova\Repeatables;

use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Repeater\Repeatable;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * A structured editor block for one command variable, used by the bundled
 * Command resource's Repeater field instead of hand-written JSON.
 */
class Variable extends Repeatable
{
    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Name', 'name')
                ->rules('required', 'string', 'max:100')
                ->help('The {placeholder} referenced in the run string.'),

            Text::make('Label', 'label')
                ->rules('nullable', 'string', 'max:255'),

            Select::make('Type', 'type')
                ->options([
                    'text' => 'Text',
                    'select' => 'Select',
                    'model' => 'Model (searchable)',
                ])
                ->default('text')
                ->rules('required', 'in:text,select,model'),

            Boolean::make('Required', 'required')->default(true),

            Text::make('Default', 'default')->rules('nullable', 'string'),

            Text::make('Placeholder', 'placeholder')->rules('nullable', 'string'),

            Text::make('Help', 'help')->rules('nullable', 'string'),

            Textarea::make('Options', 'options')
                ->rows(3)
                ->rules('nullable', 'string')
                ->help('For a Select variable. One option per line as value:Label, e.g. "DK:Denmark".'),

            Text::make('Rules', 'rules')
                ->rules('nullable', 'string')
                ->help('Extra Laravel validation rules, pipe-separated, e.g. "string|max:255".'),

            Text::make('Model class', 'model')
                ->rules('nullable', 'string')
                ->help('For a Model variable. Fully-qualified Eloquent class, e.g. "App\\Models\\Club". Must be allow-listed in searchable_models.'),

            Text::make('Value column', 'value_column')
                ->rules('nullable', 'string')
                ->help('For a Model variable. Defaults to "id".'),

            Text::make('Label column', 'label_column')
                ->rules('nullable', 'string')
                ->help('For a Model variable. Defaults to "name".'),

            Text::make('Search columns', 'search_columns')
                ->rules('nullable', 'string')
                ->help('For a Model variable. Comma-separated, e.g. "name,slug". Defaults to the label column.'),
        ];
    }
}

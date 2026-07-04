<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Nova\Support;

use Closure;
use Farsi\NovaCommandCenter\Support\RepeaterBlocks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\Repeater\Presets\JSON;
use Laravel\Nova\Fields\Repeater\RepeatableCollection;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Nova's stock JSON preset assumes the stored value is already in the
 * Repeater's own block shape and crashes on anything else. This subclass
 * normalizes the value first (via {@see RepeaterBlocks}),
 * so commands stored as config-style maps — the natural result of migrating
 * from the config source to the database source — open cleanly in the editor.
 * Writing is inherited untouched, so edits are saved in the standard shape.
 */
class NormalizingJsonPreset extends JSON
{
    public function __construct(private readonly Closure $normalize) {}

    /**
     * @return Collection
     */
    public function get(NovaRequest $request, Model $model, string $attribute, RepeatableCollection $repeatables)
    {
        return RepeatableCollection::make(($this->normalize)($model->{$attribute}))
            ->map(static function (array $block) use ($repeatables) {
                return $repeatables->newRepeatableByKey($block['type'], $block['fields']);
            });
    }
}

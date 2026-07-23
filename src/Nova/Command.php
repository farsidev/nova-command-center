<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Nova;

use Farsi\NovaCommandCenter\Models\Command as CommandModel;
use Farsi\NovaCommandCenter\Nova\Repeatables\Flag;
use Farsi\NovaCommandCenter\Nova\Repeatables\Variable;
use Farsi\NovaCommandCenter\Nova\Support\NormalizingJsonPreset;
use Farsi\NovaCommandCenter\Support\RepeaterBlocks;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Repeater;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

/**
 * Optional Nova resource for managing commands in the database.
 *
 * This class is NOT registered automatically. To use it, opt into the database
 * source (config: source.driver = "database"), publish and run the migration,
 * then register this resource from your own NovaServiceProvider — ideally behind
 * a strict policy so only trusted operators can edit the allow-list:
 *
 *     use Farsi\NovaCommandCenter\Nova\Command;
 *
 *     Nova::resources([Command::class]);
 *
 * SECURITY: whoever can create or edit these rows decides what the tool will run.
 * Protect it with a policy (`CommandPolicy`) and keep bash disabled unless needed.
 *
 * @extends resource<CommandModel>
 */
class Command extends Resource
{
    /**
     * @var class-string<CommandModel>
     */
    public static $model = CommandModel::class;

    /**
     * @var string
     */
    public static $title = 'name';

    /**
     * @var array<int, string>
     */
    public static $search = ['name', 'run', 'group'];

    public static function label(): string
    {
        return 'Commands';
    }

    public static function singularLabel(): string
    {
        return 'Command';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'string', 'max:255')
                ->creationRules('unique:nova_command_center_commands,name')
                ->updateRules('unique:nova_command_center_commands,name,{{resourceId}}'),

            Text::make('Run')
                ->rules('required', 'string', 'max:1000')
                ->help('The command to run, e.g. "migrate --force" or "cache:forget {key}".'),

            Select::make('Command type', 'command_type')
                ->options(['artisan' => 'Artisan', 'bash' => 'Shell (bash)'])
                ->default('artisan')
                ->displayUsingLabels()
                ->rules('required', 'in:artisan,bash'),

            Text::make('Group')->default('General')->sortable(),

            Select::make('Button style', 'type')
                ->options([
                    'primary' => 'Primary',
                    'success' => 'Success',
                    'warning' => 'Warning',
                    'danger' => 'Danger',
                ])
                ->default('primary')
                ->displayUsingLabels(),

            Textarea::make('Help')->nullable()->hideFromIndex(),

            Number::make('Timeout')->nullable()->min(1)->hideFromIndex()
                ->help('Maximum seconds before the process is killed.'),

            Number::make('Output size')->nullable()->min(0)->hideFromIndex()
                ->help('Number of trailing output lines to display.'),

            Boolean::make('Enabled')->default(true)->sortable(),

            Number::make('Position')->default(0)->hideFromIndex(),

            Code::make('Queue')->json()->nullable()->hideFromIndex()
                ->help('null / false to run synchronously, or {"connection": null, "queue": null}.'),

            Text::make('Authorize ability', 'can')->nullable()->hideFromIndex()
                ->help('Optional gate ability required to run this command.'),

            Select::make('Confirm before run', 'confirm')
                ->options([
                    'default' => 'Default (danger / warning types)',
                    'always' => 'Always ask',
                    'never' => 'Never ask',
                ])
                ->displayUsingLabels()
                ->resolveUsing(static fn ($value): string => match (true) {
                    $value === true => 'always',
                    $value === false => 'never',
                    default => 'default',
                })
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute): void {
                    $model->{$attribute} = match ($request[$requestAttribute] ?? 'default') {
                        'always' => true,
                        'never' => false,
                        default => null,
                    };
                })
                ->nullable()
                ->hideFromIndex()
                ->help('Overrides the type-based default. Danger and warning buttons confirm unless Never is chosen.'),

            ...$this->variableAndFlagFields(),
        ];
    }

    /**
     * Structured, repeatable editors for variables and flags on Nova 4.24+
     * (where the Repeater field exists), with a raw-JSON fallback on older
     * Nova 4 releases. Read-only JSON is still shown on the detail view for
     * transparency about what is actually stored.
     *
     * @return array<int, Field>
     */
    protected function variableAndFlagFields(): array
    {
        if (!class_exists(Repeater::class)) {
            return [
                Code::make('Variables')->json()->nullable()->hideFromIndex(),
                Code::make('Flags')->json()->nullable()->hideFromIndex(),
            ];
        }

        return [
            Repeater::make('Variables', 'variables')
                ->repeatables([Variable::make()])
                ->preset(new NormalizingJsonPreset(RepeaterBlocks::variables())),

            Repeater::make('Flags', 'flags')
                ->repeatables([Flag::make()])
                ->preset(new NormalizingJsonPreset(RepeaterBlocks::flags())),

            Code::make('Variables', 'variables')->json()->exceptOnForms(),

            Code::make('Flags', 'flags')->json()->exceptOnForms(),
        ];
    }
}

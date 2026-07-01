<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Nova;

use Farsidev\NovaCommandCenter\Models\Command as CommandModel;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
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
 *     use Farsidev\NovaCommandCenter\Nova\Command;
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

            Code::make('Variables')->json()->nullable()->hideFromIndex(),

            Code::make('Flags')->json()->nullable()->hideFromIndex(),
        ];
    }
}

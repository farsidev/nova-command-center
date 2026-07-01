<?php

declare(strict_types=1);
use Farsidev\NovaCommandCenter\Models\Command;

return [

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | The label shown in the Nova sidebar and the help text rendered at the top
    | of the tool page. Both are passed through Nova's localization layer.
    |
    */

    'navigation_label' => 'Command Center',

    'help' => 'Run pre-approved Artisan and shell commands directly from Nova.',

    /*
    |--------------------------------------------------------------------------
    | Command Source
    |--------------------------------------------------------------------------
    |
    | Where the allow-list of commands is read from.
    |
    |   "config"    (default, recommended) Commands live in the "commands" array
    |               below — version-controlled, code-reviewed, immutable at
    |               runtime. The safest posture.
    |
    |   "database"  Commands are stored in the "nova_command_center_commands"
    |               table and can be managed through the bundled Nova resource.
    |               Publish and run the migration first:
    |                 php artisan vendor:publish --tag=nova-command-center-migrations
    |               SECURITY: this moves the allow-list out of version control —
    |               anyone who can edit those rows decides what runs. Gate the
    |               resource tightly and keep bash disabled unless required.
    |
    |   A class-string implementing Farsidev\NovaCommandCenter\Contracts\CommandSource
    |               for any other backing store.
    |
    */

    'source' => [
        'driver' => 'config',
        'model' => Command::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | History
    |--------------------------------------------------------------------------
    |
    | How many of the most recent executions to keep. History is stored in the
    | cache (no database migration required). Set to 0 to disable history.
    |
    */

    'history' => 15,

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | The cache store used for history, live output buffers and overlap locks.
    | Use "null" to fall back to the application's default cache store. A store
    | that supports atomic locks (redis, memcached, database, ...) is required
    | for the "without_overlapping" feature to work reliably.
    |
    */

    'cache_store' => null,

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Every request is checked against the "runCommand" gate ability. Define
    | the gate in your own AuthServiceProvider, or set "authorize" to null to
    | rely solely on the tool's canSee() callback. Individual commands may also
    | declare their own "can" ability (see the command "can" key below).
    |
    */

    'authorize' => 'runCommand',

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Maximum number of command executions allowed per authenticated user per
    | minute. Set to null to disable rate limiting.
    |
    */

    'rate_limit' => 30,

    /*
    |--------------------------------------------------------------------------
    | Shell (bash) Commands
    |--------------------------------------------------------------------------
    |
    | Shell command execution is DISABLED by default for safety. When enabled,
    | only commands explicitly defined below (with "command_type" => "bash")
    | may run, and their arguments are always passed to the process as an
    | escaped argument list — never interpolated into a shell string.
    |
    */

    'bash' => [
        'enabled' => false,

        // Absolute path to the working directory for shell commands. Defaults
        // to the application base path when null.
        'working_directory' => null,

        // Extra environment variables merged into the process environment.
        'env' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom (ad-hoc) Commands
    |--------------------------------------------------------------------------
    |
    | Allow operators to type a command that is not in the allow-list below.
    | This is powerful and dangerous, so it is OFF by default. Enable only the
    | command types you trust operators to run freely. Example: ['artisan'].
    |
    */

    'custom_commands' => [],

    /*
    |--------------------------------------------------------------------------
    | Concurrency
    |--------------------------------------------------------------------------
    |
    | Prevent the same command, or any command within a group, from running
    | more than once at a time. Requires a lock-capable cache store.
    |
    */

    'without_overlapping' => [
        'commands' => [],
        'groups' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Fallback values applied to every command when the command itself does not
    | override them.
    |
    */

    'defaults' => [
        'timeout' => 60,
        'output_size' => 25,
        'type' => 'primary',
    ],

    /*
    |--------------------------------------------------------------------------
    | Commands
    |--------------------------------------------------------------------------
    |
    | The allow-list of runnable commands, keyed by their display name.
    |
    | Available keys per command:
    |   run          (string, required) The command to run.
    |   command_type ("artisan"|"bash") Defaults to "artisan".
    |   group        (string) Grouping shown in the UI.
    |   type         (string) Button style: primary, danger, warning, success…
    |   help         (string) Description shown under the command.
    |   timeout      (int)    Max seconds before the process is killed.
    |   output_size  (int)    Number of trailing output lines to display.
    |   queue        (bool|array) Run on the queue. Pass an array to set the
    |                             "connection" and/or "queue" name.
    |   can          (string) Gate ability required to run this command.
    |   variables    (array)  User-supplied values, keyed by placeholder name.
    |                         Each: [label, type(text|select), options,
    |                                required(bool), default, rules].
    |   flags        (array)  Optional flags rendered as checkboxes. Each:
    |                         [label, flag, default(bool)].
    |
    */

    'commands' => [

        // ---- Cache ---------------------------------------------------------
        'Clear Application Cache' => [
            'run' => 'cache:clear',
            'group' => 'Cache',
            'type' => 'warning',
            'help' => 'Flush the application cache.',
        ],

        'Cache Configuration' => [
            'run' => 'config:cache',
            'group' => 'Cache',
            'type' => 'primary',
        ],

        'Forget Cache Key' => [
            'run' => 'cache:forget {key}',
            'group' => 'Cache',
            'type' => 'warning',
            'help' => 'Remove a single cache key.',
            'variables' => [
                'key' => [
                    'label' => 'Cache key',
                    'type' => 'text',
                    'required' => true,
                    'rules' => ['string', 'max:255'],
                ],
            ],
        ],

        // ---- Optimization --------------------------------------------------
        'Optimize' => [
            'run' => 'optimize',
            'group' => 'Optimization',
            'type' => 'primary',
            'help' => 'Cache config, routes, events and views.',
        ],

        'Clear Optimizations' => [
            'run' => 'optimize:clear',
            'group' => 'Optimization',
            'type' => 'warning',
        ],

        // ---- Database ------------------------------------------------------
        'Run Migrations' => [
            'run' => 'migrate',
            'group' => 'Database',
            'type' => 'danger',
            'flags' => [
                [
                    'label' => 'Force in production',
                    'flag' => '--force',
                    'default' => true,
                ],
            ],
        ],

        'Migration Status' => [
            'run' => 'migrate:status',
            'group' => 'Database',
            'type' => 'primary',
        ],

        // ---- Queue ---------------------------------------------------------
        'Restart Queue Workers' => [
            'run' => 'queue:restart',
            'group' => 'Queue',
            'type' => 'warning',
            'help' => 'Gracefully restart queue workers after a deploy.',
        ],

        'Retry Failed Jobs' => [
            'run' => 'queue:retry {id}',
            'group' => 'Queue',
            'type' => 'primary',
            'help' => 'Retry a failed job by id, or use "all".',
            'variables' => [
                'id' => [
                    'label' => 'Job id',
                    'type' => 'text',
                    'default' => 'all',
                    'required' => true,
                ],
            ],
        ],

        // ---- Maintenance ---------------------------------------------------
        'Maintenance Mode' => [
            'run' => 'down',
            'group' => 'Maintenance',
            'type' => 'danger',
        ],

        'Bring Application Up' => [
            'run' => 'up',
            'group' => 'Maintenance',
            'type' => 'success',
        ],

    ],

];

<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Data;

use Farsi\NovaCommandCenter\Support\Cast;
use Illuminate\Contracts\Support\Arrayable;

/**
 * The outcome of running a command.
 *
 * @implements Arrayable<string, mixed>
 */
final class ExecutionResult implements Arrayable
{
    /**
     * @param  array<string, string>  $variables  Resolved variable values used for this run.
     * @param  list<string>  $flags  Enabled flag strings (e.g. `--force`) used for this run.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $commandId,
        public readonly string $name,
        public readonly string $display,
        public readonly string $status,
        public readonly ?int $exitCode,
        public readonly string $output,
        public readonly string $startedAt,
        public readonly ?string $finishedAt = null,
        public readonly ?float $duration = null,
        public readonly ?string $ranBy = null,
        public readonly array $variables = [],
        public readonly array $flags = [],
    ) {}

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_TIMED_OUT = 'timed_out';

    public function successful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function withOutput(string $output): self
    {
        return new self(
            id: $this->id,
            commandId: $this->commandId,
            name: $this->name,
            display: $this->display,
            status: $this->status,
            exitCode: $this->exitCode,
            output: $output,
            startedAt: $this->startedAt,
            finishedAt: $this->finishedAt,
            duration: $this->duration,
            ranBy: $this->ranBy,
            variables: $this->variables,
            flags: $this->flags,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Cast::string($data['id'] ?? null),
            commandId: Cast::string($data['command_id'] ?? null),
            name: Cast::string($data['name'] ?? null),
            display: Cast::string($data['display'] ?? null),
            status: Cast::string($data['status'] ?? null, self::STATUS_PENDING),
            exitCode: isset($data['exit_code']) ? Cast::nullableInt($data['exit_code']) : null,
            output: Cast::string($data['output'] ?? null),
            startedAt: Cast::string($data['started_at'] ?? null),
            finishedAt: isset($data['finished_at']) ? Cast::nullableString($data['finished_at']) : null,
            duration: isset($data['duration']) ? Cast::nullableFloat($data['duration']) : null,
            ranBy: isset($data['ran_by']) ? Cast::nullableString($data['ran_by']) : null,
            variables: Cast::stringStringMap($data['variables'] ?? null),
            flags: Cast::stringList($data['flags'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'command_id' => $this->commandId,
            'name' => $this->name,
            'display' => $this->display,
            'status' => $this->status,
            'exit_code' => $this->exitCode,
            'output' => $this->output,
            'started_at' => $this->startedAt,
            'finished_at' => $this->finishedAt,
            'duration' => $this->duration,
            'ran_by' => $this->ranBy,
            'variables' => $this->variables,
            'flags' => $this->flags,
        ];
    }
}

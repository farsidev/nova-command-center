<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Http\Requests;

use Farsi\NovaCommandCenter\Data\CommandDefinition;
use Farsi\NovaCommandCenter\Data\CommandVariable;
use Farsi\NovaCommandCenter\Exceptions\CommandNotAllowedException;
use Farsi\NovaCommandCenter\Support\CommandRepository;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RunCommandRequest extends FormRequest
{
    private ?CommandDefinition $resolved = null;

    /**
     * Method-injected, like a controller action — {@see FormRequest::passesAuthorization()}
     * resolves this through the container, so a dependency here doesn't need
     * the service-locator `app()` call the rest of the package's constructor-DI
     * convention avoids.
     */
    public function authorize(CommandRepository $commands): bool
    {
        $command = $this->command($commands);

        if ($command === null) {
            return true; // A missing command surfaces as a 422, not a 403.
        }

        // Per-command gate, if declared.
        if ($command->can !== null && !Gate::allows($command->can, $command)) {
            return false;
        }

        return true;
    }

    /**
     * Method-injected — {@see FormRequest::getValidatorInstance()} resolves
     * this through the container the same way it resolves `authorize()`.
     *
     * @return array<string, mixed>
     */
    public function rules(CommandRepository $commands): array
    {
        $rules = [
            'command' => ['required_without:custom', 'string'],
            'custom' => ['sometimes', 'array'],
            'custom.type' => ['required_with:custom', 'string', 'in:artisan,bash'],
            'custom.run' => ['required_with:custom', 'string', 'max:1000'],
            'flags' => ['sometimes', 'array'],
            'variables' => ['sometimes', 'array'],
            'mode' => ['sometimes', 'in:sync,queue'],
        ];

        $command = $this->command($commands);

        if ($command !== null) {
            foreach ($command->variables as $variable) {
                $rules['variables.'.$variable->name] = $this->variableRules($variable, $commands);
            }
        }

        return $rules;
    }

    /**
     * A "model" variable's submitted value is checked against the real table
     * via an "exists" rule, but only when its model class is allow-listed in
     * "searchable_models" — otherwise the class string is untrusted and is
     * never instantiated.
     *
     * @return list<string>
     */
    private function variableRules(CommandVariable $variable, CommandRepository $commands): array
    {
        $rules = $variable->validationRules();

        if ($variable->type !== 'model' || $variable->model === null) {
            return $rules;
        }

        if (!in_array($variable->model, $commands->searchableModels(), true)) {
            return $rules;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $variable->model;
        $table = (new $modelClass)->getTable();

        $rules[] = 'exists:'.$table.','.$variable->valueColumn;

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $command = $this->command();
        $attributes = [];

        if ($command !== null) {
            foreach ($command->variables as $variable) {
                $attributes['variables.'.$variable->name] = $variable->label;
            }
        }

        return $attributes;
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'You are not authorized to run this command.');
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->command() === null && !$this->filled('custom')) {
                $validator->errors()->add('command', 'The selected command does not exist.');
            }
        });
    }

    /**
     * Resolve the target command (allow-listed or ad-hoc) once. Callers that
     * already have a {@see CommandRepository} to hand (the container, via
     * `authorize()`/`rules()`; the controller, via its own constructor
     * injection) should pass it — it's only pulled from the container
     * directly as a fallback for the memoized re-read after resolution.
     */
    public function command(?CommandRepository $commands = null): ?CommandDefinition
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $repository = $commands ?? app(CommandRepository::class);

        if ($this->filled('custom')) {
            $custom = $this->input('custom');

            if (is_array($custom) && is_string($custom['type'] ?? null) && is_string($custom['run'] ?? null)) {
                try {
                    return $this->resolved = $repository->makeCustom($custom['type'], $custom['run']);
                } catch (CommandNotAllowedException) {
                    return null;
                }
            }

            return null;
        }

        $id = $this->input('command');

        return $this->resolved = is_string($id) ? $repository->find($id) : null;
    }

    /**
     * Resolve every variable to a string ('' when omitted), applying defaults.
     *
     * @return array<string, string>
     */
    public function resolvedValues(): array
    {
        $command = $this->command();

        if ($command === null) {
            return [];
        }

        $submitted = $this->input('variables', []);
        $submitted = is_array($submitted) ? $submitted : [];

        $values = [];

        foreach ($command->variables as $variable) {
            $value = $submitted[$variable->name] ?? $variable->default ?? '';
            $values[$variable->name] = $this->stringify($variable, $value);
        }

        return $values;
    }

    /**
     * The trusted flag strings to append, resolved from the checked flag keys.
     *
     * @return list<string>
     */
    public function resolvedFlags(): array
    {
        $command = $this->command();

        if ($command === null) {
            return [];
        }

        $checked = $this->input('flags', []);
        $checked = is_array($checked) ? $checked : [];

        $flags = [];

        foreach ($command->flags as $flag) {
            $isChecked = $checked[$flag->key] ?? $flag->default;

            if (filter_var($isChecked, FILTER_VALIDATE_BOOLEAN)) {
                $flags[] = $flag->flag;
            }
        }

        return $flags;
    }

    /**
     * The raw ad-hoc command payload, when this request runs a custom command.
     * Queued custom commands must carry their definition with the job because
     * they are built per-request and never exist in the repository.
     *
     * @return array{type: string, run: string}|null
     */
    public function customPayload(): ?array
    {
        if (!$this->filled('custom')) {
            return null;
        }

        $custom = $this->input('custom');

        if (is_array($custom) && is_string($custom['type'] ?? null) && is_string($custom['run'] ?? null)) {
            return ['type' => $custom['type'], 'run' => $custom['run']];
        }

        return null;
    }

    public function queued(): bool
    {
        $command = $this->command();

        if ($command === null) {
            return false;
        }

        return match ($this->input('mode')) {
            'queue' => true,
            'sync' => false,
            default => $command->shouldQueue(),
        };
    }

    /**
     * @param  mixed  $value
     */
    private function stringify(CommandVariable $variable, $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        if ($value === null) {
            return '';
        }

        if (!is_scalar($value)) {
            throw ValidationException::withMessages([
                'variables.'.$variable->name => $variable->label.' must be a scalar value.',
            ]);
        }

        return trim((string) $value);
    }
}

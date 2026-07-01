<?php

declare(strict_types=1);

namespace Farsidev\NovaCommandCenter\Concerns;

use Farsidev\NovaCommandCenter\Support\ExecutionStore;

/**
 * Add to any Artisan command to report progress back to the Command Center UI.
 *
 * When the command is launched by this package, the current execution id is made
 * available to the child process. Progress written here is polled by the tool and
 * rendered as a live progress bar.
 *
 * Example:
 *
 *     $this->novaProgressStart($users->count());
 *     foreach ($users as $user) {
 *         // ...
 *         $this->novaProgressAdvance();
 *     }
 *     $this->novaProgressFinish('Done');
 */
trait InteractsWithProgress
{
    private ?string $novaExecutionId = null;

    private int $novaProgressTotal = 0;

    private int $novaProgressCurrent = 0;

    public function novaProgressStart(int $total, ?string $message = null): void
    {
        $this->novaProgressTotal = max(0, $total);
        $this->novaProgressCurrent = 0;

        $this->writeNovaProgress($message);
    }

    public function novaProgressAdvance(int $step = 1, ?string $message = null): void
    {
        $this->novaProgressCurrent += $step;

        $this->writeNovaProgress($message);
    }

    public function novaProgressSet(int $current, ?string $message = null): void
    {
        $this->novaProgressCurrent = $current;

        $this->writeNovaProgress($message);
    }

    public function novaProgressFinish(?string $message = null): void
    {
        if ($this->novaProgressTotal > 0) {
            $this->novaProgressCurrent = $this->novaProgressTotal;
        }

        $this->writeNovaProgress($message);
    }

    protected function novaExecutionId(): ?string
    {
        if ($this->novaExecutionId !== null) {
            return $this->novaExecutionId;
        }

        $id = getenv('NOVA_COMMAND_CENTER_EXECUTION');

        if ($id === false || $id === '') {
            $id = $_SERVER['NOVA_COMMAND_CENTER_EXECUTION'] ?? null;
        }

        return $this->novaExecutionId = (is_string($id) && $id !== '') ? $id : null;
    }

    private function writeNovaProgress(?string $message): void
    {
        $id = $this->novaExecutionId();

        if ($id === null || !function_exists('app')) {
            return;
        }

        $percentage = $this->novaProgressTotal > 0
            ? (int) floor(min($this->novaProgressCurrent, $this->novaProgressTotal) / $this->novaProgressTotal * 100)
            : null;

        app(ExecutionStore::class)->putProgress($id, [
            'current' => $this->novaProgressCurrent,
            'total' => $this->novaProgressTotal,
            'percentage' => $percentage,
            'message' => $message,
        ]);
    }
}

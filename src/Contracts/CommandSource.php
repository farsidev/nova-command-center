<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Contracts;

use Farsi\NovaCommandCenter\Data\CommandDefinition;
use Farsi\NovaCommandCenter\Support\CommandRepository;
use Farsi\NovaCommandCenter\Support\Sources\ConfigCommandSource;

/**
 * Supplies the raw command definitions that make up the allow-list.
 *
 * The default {@see ConfigCommandSource}
 * reads them from the published config file, but any store — a database table
 * exposed as a Nova resource, a remote service, a YAML file — can be plugged in
 * by binding a different implementation to this contract.
 *
 * Implementations return only the raw, untrusted definition arrays keyed by
 * display name. Coercion, validation and the security model still run through
 * {@see CommandRepository} and
 * {@see CommandDefinition}, so a custom source
 * can never widen the trust boundary.
 */
interface CommandSource
{
    /**
     * The raw command definitions keyed by their display name.
     *
     * Keys are typed as array-key because raw stores (a config array, a JSON
     * blob) may yield non-string keys; the repository filters those out.
     *
     * @return iterable<array-key, mixed>
     */
    public function definitions(): iterable;
}

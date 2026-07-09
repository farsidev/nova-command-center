<?php

declare(strict_types=1);

namespace Farsi\NovaCommandCenter\Support;

use Farsi\NovaCommandCenter\Data\BuiltCommand;
use Farsi\NovaCommandCenter\Data\CommandDefinition;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Turns a command definition plus user input into a safe argument vector.
 *
 * The single most important guarantee of this package lives here: user-supplied
 * variable values are substituted into the command *after* the command string
 * has been tokenised, so a value can only ever become the content of one already
 * existing argument — never a new argument, operator, pipe, or sub-shell. The
 * resulting vector is executed with Symfony Process (no shell), which keeps the
 * guarantee end to end.
 */
final class CommandBuilder
{
    public function __construct(
        private readonly string $basePath,
        private readonly ?string $phpBinary = null,
    ) {}

    /**
     * @param  array<string, string>  $values  Every command variable, resolved to a
     *                                         string ('' means "not provided").
     * @param  list<string>  $flags  The trusted flag strings to append.
     */
    public function build(CommandDefinition $command, array $values, array $flags): BuiltCommand
    {
        $arguments = $this->substitute($this->tokenize($command->run), $values);

        // Any variable that was not referenced by a {placeholder} is appended as
        // its own discrete argument (never concatenated into an existing token).
        $used = $this->usedPlaceholders($command->run);

        foreach ($values as $name => $value) {
            if ($value !== '' && !in_array($name, $used, true)) {
                $arguments[] = $value;
            }
        }

        foreach ($flags as $flag) {
            foreach ($this->tokenize($flag) as $token) {
                $arguments[] = $token;
            }
        }

        if ($command->isArtisan()) {
            $argv = array_merge([$this->phpBinary(), $this->basePath.DIRECTORY_SEPARATOR.'artisan'], $arguments);

            return new BuiltCommand($argv, 'php artisan '.$this->render($arguments));
        }

        return new BuiltCommand($arguments, $this->render($arguments));
    }

    /**
     * Split a command string into arguments, honouring single quotes, double
     * quotes and backslash escaping — exactly like a POSIX shell would, but
     * purely as a parser (nothing is executed).
     *
     * @return list<string>
     */
    public function tokenize(string $input): array
    {
        $tokens = [];
        $current = '';
        $hasToken = false;
        $length = strlen($input);
        $quote = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                } elseif ($char === '\\' && $quote === '"' && $i + 1 < $length) {
                    $current .= $input[++$i];
                } else {
                    $current .= $char;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $hasToken = true;

                continue;
            }

            if ($char === '\\' && $i + 1 < $length) {
                $current .= $input[++$i];
                $hasToken = true;

                continue;
            }

            if ($char === ' ' || $char === "\t" || $char === "\n" || $char === "\r") {
                if ($hasToken) {
                    $tokens[] = $current;
                    $current = '';
                    $hasToken = false;
                }

                continue;
            }

            $current .= $char;
            $hasToken = true;
        }

        if ($hasToken) {
            $tokens[] = $current;
        }

        return $tokens;
    }

    /**
     * Replace {placeholder} occurrences inside each token. A token whose only
     * dynamic content resolves to an empty value is dropped entirely, which is
     * what makes optional variables work (an omitted "--tag={tag}" disappears).
     *
     * @param  list<string>  $tokens
     * @param  array<string, string>  $values
     * @return list<string>
     */
    private function substitute(array $tokens, array $values): array
    {
        $result = [];

        foreach ($tokens as $token) {
            $drop = false;

            $replaced = preg_replace_callback(
                '/\{([A-Za-z0-9_.-]+)\}/',
                static function (array $matches) use ($values, &$drop): string {
                    $name = $matches[1];

                    // Unknown placeholder: leave the literal braces untouched.
                    if (!array_key_exists($name, $values)) {
                        return $matches[0];
                    }

                    if ($values[$name] === '') {
                        $drop = true;

                        return '';
                    }

                    return $values[$name];
                },
                $token,
            );

            if ($drop) {
                continue;
            }

            $result[] = (string) $replaced;
        }

        return $result;
    }

    /**
     * The {placeholder} names referenced by a run string. Public and static so
     * diagnostics (the check command) match execution exactly — same pattern,
     * one definition.
     *
     * @return list<string>
     */
    public static function placeholders(string $run): array
    {
        preg_match_all('/\{([A-Za-z0-9_.-]+)\}/', $run, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return list<string>
     */
    private function usedPlaceholders(string $run): array
    {
        return self::placeholders($run);
    }

    /**
     * Build a human-readable command line for display and history. This value is
     * never executed; arguments containing whitespace are quoted for legibility.
     *
     * @param  list<string>  $arguments
     */
    private function render(array $arguments): string
    {
        return implode(' ', array_map(static function (string $argument): string {
            return preg_match('/\s/', $argument) === 1 ? '"'.$argument.'"' : $argument;
        }, $arguments));
    }

    private function phpBinary(): string
    {
        if ($this->phpBinary !== null) {
            return $this->phpBinary;
        }

        $binary = (new PhpExecutableFinder)->find(false);

        return $binary !== false ? $binary : 'php';
    }
}

<?php

declare(strict_types=1);

arch('all source declares strict types')
    ->expect('Farsi\NovaCommandCenter')
    ->toUseStrictTypes();

arch('no debug statements leak into source')
    ->expect(['dd', 'dump', 'var_dump', 'ray', 'print_r', 'die'])
    ->not->toBeUsed();

arch('data transfer objects are immutable value objects')
    ->expect('Farsi\NovaCommandCenter\Data')
    ->toBeFinal()
    ->toHaveConstructor();

arch('contracts are interfaces')
    ->expect('Farsi\NovaCommandCenter\Contracts')
    ->toBeInterfaces();

arch('actions are final')
    ->expect('Farsi\NovaCommandCenter\Actions')
    ->toBeClasses()
    ->toBeFinal();

arch('command sources implement the contract')
    ->expect('Farsi\NovaCommandCenter\Support\Sources')
    ->toImplement('Farsi\NovaCommandCenter\Contracts\CommandSource')
    ->toBeFinal();

arch('exceptions extend the SPL exception hierarchy')
    ->expect('Farsi\NovaCommandCenter\Exceptions')
    ->toExtend('Exception');

arch('the security-critical builder never touches the shell')
    ->expect('Farsi\NovaCommandCenter\Support\CommandBuilder')
    ->not->toUse(['shell_exec', 'exec', 'system', 'passthru', 'proc_open', 'popen']);

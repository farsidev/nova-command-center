<?php

declare(strict_types=1);

arch('all source declares strict types')
    ->expect('Farsidev\NovaCommandCenter')
    ->toUseStrictTypes();

arch('no debug statements leak into source')
    ->expect(['dd', 'dump', 'var_dump', 'ray', 'print_r', 'die'])
    ->not->toBeUsed();

arch('data transfer objects are immutable value objects')
    ->expect('Farsidev\NovaCommandCenter\Data')
    ->toBeFinal()
    ->toHaveConstructor();

arch('contracts are interfaces')
    ->expect('Farsidev\NovaCommandCenter\Contracts')
    ->toBeInterfaces();

arch('actions are final')
    ->expect('Farsidev\NovaCommandCenter\Actions')
    ->toBeClasses()
    ->toBeFinal();

arch('command sources implement the contract')
    ->expect('Farsidev\NovaCommandCenter\Support\Sources')
    ->toImplement('Farsidev\NovaCommandCenter\Contracts\CommandSource')
    ->toBeFinal();

arch('exceptions extend the SPL exception hierarchy')
    ->expect('Farsidev\NovaCommandCenter\Exceptions')
    ->toExtend('Exception');

arch('the security-critical builder never touches the shell')
    ->expect('Farsidev\NovaCommandCenter\Support\CommandBuilder')
    ->not->toUse(['shell_exec', 'exec', 'system', 'passthru', 'proc_open', 'popen']);

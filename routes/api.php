<?php

declare(strict_types=1);

use Farsidev\NovaCommandCenter\Http\Controllers\CommandController;
use Farsidev\NovaCommandCenter\Http\Controllers\ExecutionController;
use Farsidev\NovaCommandCenter\Http\Controllers\HistoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tool API Routes
|--------------------------------------------------------------------------
|
| Prefixed with "nova-vendor/farsidev/nova-command-center" and guarded by
| Nova's middleware plus the tool Authorize middleware.
|
*/

Route::get('/commands', [CommandController::class, 'index'])->name('nova-command-center.commands');
Route::post('/commands/run', [CommandController::class, 'run'])->name('nova-command-center.run');
Route::get('/executions/{execution}', [ExecutionController::class, 'show'])->name('nova-command-center.execution');
Route::get('/history', [HistoryController::class, 'index'])->name('nova-command-center.history');
Route::delete('/history', [HistoryController::class, 'destroy'])->name('nova-command-center.history.clear');

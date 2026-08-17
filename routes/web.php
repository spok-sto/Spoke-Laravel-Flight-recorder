<?php

use Illuminate\Support\Facades\Route;
use Konekt\Spoke\Http\Controllers\SpokeApiController;
use Konekt\Spoke\Http\Controllers\SpokeController;

Route::group([
    'prefix' => config('spoke.path'),
    'middleware' => config('spoke.middleware'),
    'as' => 'spoke.',
], function () {
    Route::get('/', [SpokeController::class, 'index'])->name('index');

    Route::prefix('api')->as('api.')->group(function () {
        Route::get('/logs', [SpokeApiController::class, 'logs'])->name('logs');
        Route::get('/queries', [SpokeApiController::class, 'queries'])->name('queries');
        Route::get('/queries/stats', [SpokeApiController::class, 'queryStats'])->name('queries.stats');
        Route::get('/requests', [SpokeApiController::class, 'requests'])->name('requests');
        Route::get('/mails', [SpokeApiController::class, 'mails'])->name('mails');
        Route::get('/queue', [SpokeApiController::class, 'queue'])->name('queue');
        Route::post('/queue/retry', [SpokeApiController::class, 'queueRetry'])->name('queue.retry');
        Route::post('/queue/forget', [SpokeApiController::class, 'queueForget'])->name('queue.forget');
        Route::get('/jobs', [SpokeApiController::class, 'jobs'])->name('jobs');
        Route::get('/jobs/stats', [SpokeApiController::class, 'jobStats'])->name('jobs.stats');
        Route::get('/scheduler', [SpokeApiController::class, 'scheduler'])->name('scheduler');
        Route::get('/commands', [SpokeApiController::class, 'commands'])->name('commands');
        Route::get('/redis', [SpokeApiController::class, 'redis'])->name('redis');
        Route::get('/redis/commands', [SpokeApiController::class, 'redisCommands'])->name('redis.commands');
        Route::get('/http', [SpokeApiController::class, 'httpCalls'])->name('http');
        Route::get('/trace/{traceId}', [SpokeApiController::class, 'trace'])->name('trace');
        Route::get('/server', [SpokeApiController::class, 'server'])->name('server');
        Route::get('/exceptions', [SpokeApiController::class, 'exceptions'])->name('exceptions');
        Route::get('/exceptions/groups', [SpokeApiController::class, 'exceptionGroups'])->name('exceptions.groups');
        Route::get('/health', [SpokeApiController::class, 'health'])->name('health');
        Route::get('/capture', [SpokeApiController::class, 'captureState'])->name('capture');

        Route::middleware(\Konekt\Spoke\Http\Middleware\RequireSpokeDebugTools::class)->group(function () {
            Route::post('/queries/explain', [SpokeApiController::class, 'queryExplain'])->name('queries.explain');
            Route::get('/mails/body', [SpokeApiController::class, 'mailBody'])->name('mails.body');
            Route::get('/redis/keys', [SpokeApiController::class, 'redisKeys'])->name('redis.keys');
            Route::get('/redis/key', [SpokeApiController::class, 'redisKey'])->name('redis.key');
            Route::post('/capture', [SpokeApiController::class, 'captureToggle'])->name('capture.toggle');
            Route::get('/capture/payloads', [SpokeApiController::class, 'capturePayloads'])->name('capture.payloads');
        });
    });
});

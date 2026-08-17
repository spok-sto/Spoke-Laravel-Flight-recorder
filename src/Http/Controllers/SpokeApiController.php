<?php

declare(strict_types=1);

namespace Konekt\Spoke\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Konekt\Spoke\Readers\ExceptionStatsReader;
use Konekt\Spoke\Readers\HealthReader;
use Konekt\Spoke\Readers\JobStatsReader;
use Konekt\Spoke\Readers\JsonlReader;
use Konekt\Spoke\Readers\LaravelLogReader;
use Konekt\Spoke\Readers\QueryStatsReader;
use Konekt\Spoke\Readers\QueueReader;
use Konekt\Spoke\Readers\RedisInfoReader;
use Konekt\Spoke\Readers\ServerInfoReader;
use Konekt\Spoke\Readers\TraceReader;
use Konekt\Spoke\Support\CaptureMode;
use Konekt\Spoke\Support\QueryExplainer;

class SpokeApiController extends Controller
{
    public function logs(Request $request, LaravelLogReader $reader): JsonResponse
    {
        return response()->json($reader->read(
            file: $request->query('file'),
            level: $request->query('level'),
            search: $request->query('search'),
            page: max(1, (int) $request->query('page', '1')),
            cursor: $request->query('cursor') !== null
                ? max(0, (int) $request->query('cursor'))
                : null,
        ));
    }

    public function queries(Request $request, JsonlReader $reader): JsonResponse
    {
        return response()->json($reader->read(
            type: 'queries',
            date: $request->query('date'),
            search: $request->query('search'),
            page: max(1, (int) $request->query('page', '1')),
        ));
    }

    public function queryStats(Request $request, QueryStatsReader $reader): JsonResponse
    {
        return response()->json($reader->ranking(
            date: $request->query('date'),
            sort: (string) $request->query('sort', 'total_ms'),
            search: $request->query('search'),
        ));
    }

    public function queryExplain(Request $request, QueryExplainer $explainer): JsonResponse
    {
        $validated = $request->validate([
            'sql' => 'required|string|max:5000',
            'bindings' => 'nullable|array|max:50',
            'conn' => 'nullable|string|max:64',
            'analyze' => 'sometimes|boolean',
        ]);

        $result = $explainer->explain(
            sql: (string) $validated['sql'],
            bindings: $validated['bindings'] ?? [],
            connection: $validated['conn'] ?? null,
            analyze: (bool) ($validated['analyze'] ?? false),
        );

        $status = ! empty($result['ok']) ? 200 : 422;

        return response()->json($result, $status);
    }

    public function requests(Request $request, JsonlReader $reader): JsonResponse
    {
        return response()->json($reader->read(
            type: 'requests',
            date: $request->query('date'),
            search: $request->query('search'),
            page: max(1, (int) $request->query('page', '1')),
        ));
    }

    public function mails(Request $request, JsonlReader $reader): JsonResponse
    {
        return response()->json($reader->read(
            type: 'mails',
            date: $request->query('date'),
            search: $request->query('search'),
            page: max(1, (int) $request->query('page', '1')),
        ));
    }

    public function mailBody(Request $request): Response
    {
        $dir = realpath(config('spoke.mail_body_dir'));
        $path = realpath(config('spoke.mail_body_dir') . '/' . basename((string) $request->query('file')));

        abort_if(
            $dir === false || $path === false || ! str_starts_with($path, $dir) || ! is_file($path),
            404
        );

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; img-src data: https: http:; style-src 'unsafe-inline' data:; font-src data:",
        ]);
    }

    public function queue(QueueReader $reader): JsonResponse
    {
        return response()->json($reader->overview());
    }

    public function queueRetry(Request $request, QueueReader $reader): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|string|max:255|required_without:uuid',
            'uuid' => 'nullable|string|max:255|required_without:id',
        ]);
        $identifier = (string) ($validated['id'] ?? $validated['uuid']);

        return response()->json($reader->retry($identifier));
    }

    public function queueForget(Request $request, QueueReader $reader): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|string|max:255|required_without:uuid',
            'uuid' => 'nullable|string|max:255|required_without:id',
        ]);
        $identifier = (string) ($validated['id'] ?? $validated['uuid']);

        return response()->json($reader->forget($identifier));
    }

    public function jobs(Request $request, JsonlReader $reader): JsonResponse
    {
        return response()->json($reader->read(
            type: 'jobs',
            date: $request->query('date'),
            search: $request->query('search'),
            page: max(1, (int) $request->query('page', '1')),
        ));
    }

    public function jobStats(Request $request, JobStatsReader $reader): JsonResponse
    {
        return response()->json($reader->stats(
            date: $request->query('date'),
        ));
    }

    public function scheduler(Request $request, JsonlReader $reader): JsonResponse
    {
        return response()->json($reader->read(
            type: 'scheduler',
            date: $request->query('date'),
            search: $request->query('search'),
            page: max(1, (int) $request->query('page', '1')),
        ));
    }

    public function commands(Request $request, JsonlReader $reader): JsonResponse
    {
        return response()->json($reader->read(
            type: 'commands',
            date: $request->query('date'),
            search: $request->query('search'),
            page: max(1, (int) $request->query('page', '1')),
        ));
    }

    public function redis(RedisInfoReader $reader): JsonResponse
    {
        return response()->json($reader->info());
    }

    public function redisKeys(Request $request, RedisInfoReader $reader): JsonResponse
    {
        return response()->json($reader->listKeys(
            connection: (string) $request->query('connection', 'default'),
            pattern: (string) $request->query('pattern', '*'),
            limit: (int) $request->query('limit', '100'),
        ));
    }

    public function redisKey(Request $request, RedisInfoReader $reader): JsonResponse
    {
        return response()->json($reader->getKey(
            connection: (string) $request->query('connection', 'default'),
            key: (string) $request->query('key', ''),
        ));
    }

    public function redisCommands(Request $request, JsonlReader $reader): JsonResponse
    {
        return response()->json($reader->read(
            type: 'redis',
            date: $request->query('date'),
            search: $request->query('search'),
            page: max(1, (int) $request->query('page', '1')),
        ));
    }

    public function httpCalls(Request $request, JsonlReader $reader): JsonResponse
    {
        return response()->json($reader->read(
            type: 'http',
            date: $request->query('date'),
            search: $request->query('search'),
            page: max(1, (int) $request->query('page', '1')),
        ));
    }

    public function trace(Request $request, string $traceId, TraceReader $reader): JsonResponse
    {
        $traceId = trim($traceId);

        abort_if($traceId === '' || strlen($traceId) > 64, 404);

        return response()->json($reader->find(
            traceId: $traceId,
            date: $request->query('date'),
        ));
    }

    public function server(ServerInfoReader $reader): JsonResponse
    {
        return response()->json($reader->info());
    }

    public function health(Request $request, HealthReader $reader): JsonResponse
    {
        return response()->json($reader->snapshot(
            date: $request->query('date'),
        ));
    }

    public function captureState(CaptureMode $capture): JsonResponse
    {
        return response()->json($capture->state());
    }

    public function captureToggle(Request $request, CaptureMode $capture): JsonResponse
    {
        $state = $request->boolean('active')
            ? $capture->enable()
            : $capture->disable();

        return response()->json($state);
    }

    public function capturePayloads(Request $request, JsonlReader $reader): JsonResponse
    {
        return response()->json($reader->read(
            type: 'capture',
            date: $request->query('date'),
            search: $request->query('trace_id') ?: $request->query('search'),
            page: max(1, (int) $request->query('page', '1')),
        ));
    }

    public function exceptions(Request $request, JsonlReader $reader): JsonResponse
    {
        return response()->json($reader->read(
            type: 'exceptions',
            date: $request->query('date'),
            search: $request->query('search'),
            page: max(1, (int) $request->query('page', '1')),
        ));
    }

    public function exceptionGroups(Request $request, ExceptionStatsReader $reader): JsonResponse
    {
        return response()->json($reader->grouping(
            date: $request->query('date'),
            search: $request->query('search'),
        ));
    }
}

<?php

declare(strict_types=1);

namespace Konekt\Spoke;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;
use Konekt\Spoke\Recorders\CommandRecorder;
use Konekt\Spoke\Recorders\ExceptionRecorder;
use Konekt\Spoke\Recorders\HttpClientRecorder;
use Konekt\Spoke\Recorders\JobRecorder;
use Konekt\Spoke\Recorders\MailRecorder;
use Konekt\Spoke\Recorders\QueryRecorder;
use Konekt\Spoke\Recorders\RedisCommandRecorder;
use Konekt\Spoke\Recorders\RequestRecorder;
use Konekt\Spoke\Recorders\SchedulerRecorder;
use Konekt\Spoke\Support\CaptureMode;
use Konekt\Spoke\Support\DeploymentMarker;
use Konekt\Spoke\Support\JsonlWriter;
use Konekt\Spoke\Support\MetricsSampler;
use Konekt\Spoke\Support\NPlusOneDetector;
use Konekt\Spoke\Support\TraceContext;
use Throwable;

class SpokeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/spoke.php', 'spoke');

        $this->app->singleton(JsonlWriter::class);
        $this->app->singleton(NPlusOneDetector::class);
        $this->app->singleton(QueryRecorder::class);
        $this->app->singleton(RequestRecorder::class);
        $this->app->singleton(MailRecorder::class);
        $this->app->singleton(RedisCommandRecorder::class);
        $this->app->singleton(HttpClientRecorder::class);
        $this->app->singleton(JobRecorder::class);
        $this->app->singleton(ExceptionRecorder::class);
        $this->app->singleton(SchedulerRecorder::class);
        $this->app->singleton(CommandRecorder::class);
        $this->app->singleton(DeploymentMarker::class);
        $this->app->singleton(MetricsSampler::class);
        $this->app->singleton(CaptureMode::class);

        $this->app->scoped(TraceContext::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/spoke.php' => config_path('spoke.php'),
        ], 'spoke-config');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'spoke');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Konekt\Spoke\Console\PruneCommand::class,
                \Konekt\Spoke\Console\RollupCommand::class,
                \Konekt\Spoke\Console\SampleCommand::class,
            ]);
        }

        $this->registerGate();

        if (! config('spoke.enabled')) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $this->registerRecorders();

        if (config('spoke.deploy.detect')) {
            try {
                $this->app->make(DeploymentMarker::class)->recordIfChanged();
            } catch (Throwable $e) {
            }
        }
    }

    protected function registerGate(): void
    {
        if (Gate::has('viewSpoke')) {
            return;
        }

        Gate::define('viewSpoke', function ($user = null) {
            return config('spoke.auth.enabled') || app()->environment('local', 'dev', 'development');
        });
    }

    protected function registerRecorders(): void
    {
        if (config('spoke.recorders.queries.enabled')) {
            $queryRecorder = $this->app->make(QueryRecorder::class);
            DB::listen(static function ($query) use ($queryRecorder) {
                $queryRecorder->record($query);
            });
        }

        if (config('spoke.recorders.requests.enabled')) {
            $requestRecorder = $this->app->make(RequestRecorder::class);
            Event::listen(RequestHandled::class, static function (RequestHandled $event) use ($requestRecorder) {
                $requestRecorder->record($event);
            });
        }

        if (config('spoke.recorders.mails.enabled')) {
            $mailRecorder = $this->app->make(MailRecorder::class);
            Event::listen(MessageSending::class, static function (MessageSending $event) use ($mailRecorder) {
                $mailRecorder->record($event);
            });
        }

        if (config('spoke.recorders.redis.enabled')) {
            $this->registerRedisRecorder();
        }

        if (config('spoke.recorders.http_client.enabled')) {
            $httpRecorder = $this->app->make(HttpClientRecorder::class);
            Event::listen(RequestSending::class, static function (RequestSending $event) use ($httpRecorder) {
                $httpRecorder->recordSending($event);
            });
            Event::listen(ResponseReceived::class, static function (ResponseReceived $event) use ($httpRecorder) {
                $httpRecorder->recordReceived($event);
            });
            Event::listen(ConnectionFailed::class, static function (ConnectionFailed $event) use ($httpRecorder) {
                $httpRecorder->recordFailed($event);
            });
        }

        if (config('spoke.recorders.jobs.enabled')) {
            JobRecorder::registerPayloadHooks();
            $jobRecorder = $this->app->make(JobRecorder::class);
            Event::listen(JobQueued::class, static function (JobQueued $event) use ($jobRecorder) {
                $jobRecorder->recordQueued($event);
            });
            Event::listen(JobProcessing::class, static function (JobProcessing $event) use ($jobRecorder) {
                $jobRecorder->recordProcessing($event);
            });
            Event::listen(JobProcessed::class, static function (JobProcessed $event) use ($jobRecorder) {
                $jobRecorder->recordProcessed($event);
            });
            Event::listen(JobFailed::class, static function (JobFailed $event) use ($jobRecorder) {
                $jobRecorder->recordFailed($event);
            });
        }

        if (config('spoke.recorders.exceptions.enabled')) {
            $exceptionRecorder = $this->app->make(ExceptionRecorder::class);
            Event::listen(MessageLogged::class, static function (MessageLogged $event) use ($exceptionRecorder) {
                $exceptionRecorder->record($event);
            });
        }

        if (config('spoke.metrics.enabled') && config('spoke.metrics.sample_web_opcache')) {
            $metricsSampler = $this->app->make(MetricsSampler::class);
            Event::listen(RequestHandled::class, static function () use ($metricsSampler) {
                $metricsSampler->sampleWebRuntime();
            });
        }

        if (config('spoke.recorders.scheduler.enabled')) {
            $schedulerRecorder = $this->app->make(SchedulerRecorder::class);
            Event::listen(ScheduledTaskFinished::class, static function (ScheduledTaskFinished $event) use ($schedulerRecorder) {
                $schedulerRecorder->recordFinished($event);
            });
            Event::listen(ScheduledTaskFailed::class, static function (ScheduledTaskFailed $event) use ($schedulerRecorder) {
                $schedulerRecorder->recordFailed($event);
            });
        }

        if (config('spoke.recorders.commands.enabled')) {
            $commandRecorder = $this->app->make(CommandRecorder::class);
            Event::listen(CommandStarting::class, static function (CommandStarting $event) use ($commandRecorder) {
                $commandRecorder->recordStarting($event);
            });
            Event::listen(CommandFinished::class, static function (CommandFinished $event) use ($commandRecorder) {
                $commandRecorder->recordFinished($event);
            });
        }
    }

    protected function registerRedisRecorder(): void
    {
        try {
            Redis::enableEvents();
            $redisRecorder = $this->app->make(RedisCommandRecorder::class);
            Redis::listen(static function (CommandExecuted $event) use ($redisRecorder) {
                $redisRecorder->record($event);
            });
        } catch (Throwable $e) {
        }
    }
}

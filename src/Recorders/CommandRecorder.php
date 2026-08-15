<?php

declare(strict_types=1);

namespace Konekt\Spoke\Recorders;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Konekt\Spoke\Support\JsonlWriter;
use Symfony\Component\Console\Input\InputInterface;
use Throwable;

/**
 * Opciono snima artisan komande u commands-*.jsonl.
 * Default je isključen — schedule:run / queue:work su previše bučni.
 */
class CommandRecorder
{
    /** @var array<int, float> */
    private array $startedAt = [];

    public function __construct(private JsonlWriter $writer)
    {
    }

    /**
     * Početak artisan komande (za merenje trajanja).
     */
    public function recordStarting(CommandStarting $event): void
    {
        try {
            if ($this->shouldIgnore($event->command)) {
                return;
            }

            if ($event->input instanceof InputInterface) {
                $this->startedAt[spl_object_id($event->input)] = microtime(true);
                if (count($this->startedAt) > 50) {
                    $this->startedAt = array_slice($this->startedAt, -25, 25, true);
                }
            }
        } catch (Throwable $e) {
        }
    }

    /**
     * Završetak artisan komande.
     */
    public function recordFinished(CommandFinished $event): void
    {
        try {
            $name = (string) $event->command;

            if ($this->shouldIgnore($name)) {
                return;
            }

            $ms = null;
            if ($event->input instanceof InputInterface) {
                $id = spl_object_id($event->input);
                if (isset($this->startedAt[$id])) {
                    $ms = round((microtime(true) - $this->startedAt[$id]) * 1000, 2);
                    unset($this->startedAt[$id]);
                }
            }

            $this->writer->write('commands', [
                't' => now()->format('Y-m-d H:i:s.v'),
                'command' => mb_substr($name !== '' ? $name : 'unknown', 0, 200),
                'exit_code' => $event->exitCode,
                'ms' => $ms,
            ]);
        } catch (Throwable $e) {
        }
    }

    private function shouldIgnore(?string $command): bool
    {
        $command = (string) $command;

        if ($command === '') {
            return true;
        }

        foreach (config('spoke.recorders.commands.ignore', []) as $pattern) {
            if (fnmatch((string) $pattern, $command)) {
                return true;
            }
        }

        return false;
    }
}

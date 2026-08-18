<?php

declare(strict_types=1);

namespace Konekt\Spoke\Console;

use Illuminate\Console\Command;
use Konekt\Spoke\Support\DailyRollup;

class RollupCommand extends Command
{
    protected $signature = 'spoke:rollup
        {--date= : Aggregate a single date (Y-m-d, default: yesterday)}
        {--days= : Aggregate the last N days instead of a single date}';

    protected $description = 'Aggregate one day of Spoke telemetry into a long-term daily rollup';

    public function handle(DailyRollup $rollup): int
    {
        if (! config('spoke.rollup.enabled', false)) {
            $this->line('Spoke rollup is disabled (spoke.rollup.enabled).');

            return self::SUCCESS;
        }

        $written = 0;

        foreach ($this->dates() as $date) {
            if ($rollup->store($rollup->build($date))) {
                $written++;
            } else {
                $this->warn('Spoke rollup failed for ' . $date . '.');
            }
        }

        $this->info(sprintf('Spoke rollup: wrote %d day(s).', $written));

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function dates(): array
    {
        $date = $this->option('date');

        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return [$date];
        }

        $days = (int) ($this->option('days') ?? 0);

        if ($days > 0) {
            $dates = [];

            for ($i = $days; $i >= 1; $i--) {
                $dates[] = date('Y-m-d', time() - ($i * 86400));
            }

            return $dates;
        }

        return [date('Y-m-d', time() - 86400)];
    }
}

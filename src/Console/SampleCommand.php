<?php

declare(strict_types=1);

namespace Konekt\Spoke\Console;

use Illuminate\Console\Command;
use Konekt\Spoke\Support\MetricsSampler;

class SampleCommand extends Command
{
    protected $signature = 'spoke:sample';

    protected $description = 'Record one server metrics sample for the Spoke history charts';

    public function handle(MetricsSampler $sampler): int
    {
        $row = $sampler->sample();

        if ($row === null) {
            $this->line('Spoke metrics sampling is disabled (spoke.metrics.enabled).');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Spoke sample: load %s%%, memory %s%%, disk %s%%.',
            $row['load_pct'] ?? 'n/a',
            $row['mem_used_pct'] ?? 'n/a',
            $row['disk_used_pct'] ?? 'n/a'
        ));

        return self::SUCCESS;
    }
}

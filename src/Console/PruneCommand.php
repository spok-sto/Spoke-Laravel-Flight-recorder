<?php

declare(strict_types=1);

namespace Konekt\Spoke\Console;

use Illuminate\Console\Command;
use Konekt\Spoke\Support\JsonlWriter;

/**
 * Briše Spoke telemetriju stariju od retention perioda.
 * Namenjeno za scheduler/cron — ne zavisi od saobraćaja na aplikaciji.
 */
class PruneCommand extends Command
{
    protected $signature = 'spoke:prune {--days= : Broj dana za čuvanje (default: spoke.retention_days)}';

    protected $description = 'Briše Spoke JSONL telemetriju i mail fajlove starije od retention perioda';

    public function handle(JsonlWriter $writer): int
    {
        $days = $this->option('days');
        $deleted = $writer->prune($days !== null ? (int) $days : null);

        $this->info(sprintf(
            'Spoke prune: obrisano %d fajlova (retention: %s dana).',
            $deleted,
            $days ?? config('spoke.retention_days', 7)
        ));

        return self::SUCCESS;
    }
}

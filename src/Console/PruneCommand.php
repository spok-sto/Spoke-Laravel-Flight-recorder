<?php

declare(strict_types=1);

namespace Konekt\Spoke\Console;

use Illuminate\Console\Command;
use Konekt\Spoke\Support\JsonlWriter;

class PruneCommand extends Command
{
    protected $signature = 'spoke:prune {--days= : Retention days (default: spoke.retention_days)}';

    protected $description = 'Delete Spoke JSONL telemetry and mail files older than the retention period';

    public function handle(JsonlWriter $writer): int
    {
        $days = $this->option('days');
        $deleted = $writer->prune($days !== null ? (int) $days : null);

        $this->info(sprintf(
            'Spoke prune: deleted %d files (retention: %s days).',
            $deleted,
            $days ?? config('spoke.retention_days', 7)
        ));

        return self::SUCCESS;
    }
}

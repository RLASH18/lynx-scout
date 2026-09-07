<?php

declare(strict_types=1);

namespace Lynx\Scout\Commands;

use Illuminate\Console\Command;
use Lynx\Scout\Reports\ReportGenerator;
use Lynx\Scout\Services\LynxScanner;

class ReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lynx:report {--min-severity= : Minimum severity level to include}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a detailed human-readable performance findings report';

    /**
     * Execute the console command.
     */
    public function handle(LynxScanner $scanner, ReportGenerator $generator): int
    {
        $findings = $scanner->scan();

        $minSeverity = $this->option('min-severity');
        if ($minSeverity !== null) {
            $minSev = strtolower((string) $minSeverity);
            $weights = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1, 'info' => 0];
            $minWeight = $weights[$minSev] ?? 0;

            $findings = array_values(array_filter(
                $findings,
                fn ($f): bool => ($weights[$f->getSeverity()->value] ?? 0) >= $minWeight
            ));
        }

        $reportText = $generator->generate($findings);

        foreach (explode("\n", $reportText) as $line) {
            $this->line($line);
        }

        return Command::SUCCESS;
    }
}

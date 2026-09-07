<?php

declare(strict_types=1);

namespace Lynx\Scout\Commands;

use Illuminate\Console\Command;
use Lynx\Scout\Services\LynxScanner;

class ScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lynx:scan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan application runtime performance and display prioritized findings';

    /**
     * Execute the console command.
     */
    public function handle(LynxScanner $scanner): int
    {
        $this->newLine();
        $this->info('Lynx Scout');
        $this->newLine();
        $this->line('Scanning application...');
        $this->newLine();

        $findings = $scanner->scan();

        $this->line('<info>✓</info> Requests analyzed');
        $this->line('<info>✓</info> Queries analyzed');
        $this->line('<info>✓</info> Performance patterns analyzed');
        $this->line('<info>✓</info> Findings prioritized');
        $this->newLine();

        $count = count($findings);
        if ($count === 0) {
            $this->info('No performance concerns detected. Application is healthy!');
            $this->newLine();
            return Command::SUCCESS;
        }

        $this->line(sprintf('<comment>%d finding%s detected.</comment>', $count, $count === 1 ? '' : 's'));
        $this->newLine();

        $index = 1;
        foreach ($findings as $finding) {
            $title = $finding->getTitle();
            $severity = $finding->getSeverity()->label();

            // Right-pad for dots formatting
            $dots = str_repeat('.', max(2, 40 - strlen($title)));
            $this->line(sprintf('  %d. %s %s <fg=%s>%s</>', $index++, $title, $dots, $this->severityColor($severity), $severity));
        }

        $this->newLine();
        $this->line('Run <comment>php artisan lynx:report</comment> for detailed recommendations.');
        $this->newLine();

        return Command::SUCCESS;
    }

    private function severityColor(string $severity): string
    {
        return match (strtolower($severity)) {
            'critical' => 'red',
            'high' => 'yellow',
            'medium' => 'cyan',
            'low' => 'blue',
            default => 'white',
        };
    }
}

<?php

declare(strict_types=1);

namespace Lynx\Scout\Commands;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Lynx\Scout\Contracts\FindingRepositoryContract;
use Lynx\Scout\Data\Finding;
use Lynx\Scout\Services\LynxScanner;
use Lynx\Scout\Support\LynxCli;
use function Termwind\render;
use function Termwind\renderUsing;

class FindingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lynx:findings
                            {--severity= : Filter by severity (critical, high, medium, low, info)}
                            {--type= : Filter by finding type}
                            {--recent : Only show findings from the last 24 hours}
                            {--limit=20 : Maximum number of findings to display}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspect stored and detected performance findings with optional filters';

    /**
     * Execute the console command.
     */
    public function handle(FindingRepositoryContract $repository, LynxScanner $scanner): int
    {
        renderUsing($this->output);

        LynxCli::header('FINDINGS', 'Historical Finding Archives');

        $findings = $repository->all();

        // If repository is empty, run live scan to discover any active findings
        if (empty($findings)) {
            $findings = $scanner->scan();
        }

        // Filter by severity
        if ($severityFilter = $this->option('severity')) {
            $targetSev = strtolower((string) $severityFilter);
            $findings = array_filter(
                $findings,
                fn (Finding $f): bool => strtolower($f->getSeverity()->value) === $targetSev
            );
        }

        // Filter by type
        if ($typeFilter = $this->option('type')) {
            $targetType = str_replace('-', '_', strtolower((string) $typeFilter));
            $findings = array_filter(
                $findings,
                fn (Finding $f): bool => str_contains(strtolower($f->getType()), $targetType)
            );
        }

        // Filter by recent (last 24 hours)
        if ($this->option('recent')) {
            $cutoff = (new DateTimeImmutable())->modify('-24 hours');
            $findings = array_filter(
                $findings,
                fn (Finding $f): bool => $f->getDetectedAt() >= $cutoff
            );
        }

        $findings = array_values($findings);

        // Limit results
        $limit = max(1, (int) $this->option('limit'));
        $displayed = array_slice($findings, 0, $limit);

        if (empty($displayed)) {
            renderUsing($this->output);
            render(<<<'HTML'
                <div class="my-1">
                    <span class="px-1 bg-gray-700 text-gray-300 font-bold">INFO</span>
                    <span class="ml-1 text-gray-400">No findings found matching the criteria.</span>
                </div>
            HTML);
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($displayed as $f) {
            $occurrences = $f->getContext()['occurrence_count'] ?? 1;
            $rows[] = [
                $f->getId(),
                strtoupper($f->getSeverity()->value),
                $f->getType(),
                $f->getTitle(),
                (string) $occurrences,
                sprintf('%.1f (%s)', $f->getScore(), $f->getImpact()),
                $f->getDetectedAt()->format('Y-m-d H:i:s'),
            ];
        }

        $this->newLine();
        $this->table(
            ['ID', 'Severity', 'Type', 'Title', 'Occurrences', 'Score', 'Detected At'],
            $rows,
            'box'
        );
        $this->newLine();

        return Command::SUCCESS;
    }
}

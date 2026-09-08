<?php

declare(strict_types=1);

namespace Lynx\Scout\Commands;

use Illuminate\Console\Command;
use Lynx\Scout\Contracts\FindingRepositoryContract;
use Lynx\Scout\Reports\ReportGenerator;
use Lynx\Scout\Services\LynxScanner;
use Lynx\Scout\Support\LynxCli;

use function Termwind\render;
use function Termwind\renderUsing;

class ReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lynx:report
                            {--min-severity= : Minimum severity level to include}
                            {--json : Output report as structured JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a detailed performance findings report (text or JSON)';

    /**
     * Execute the console command.
     */
    public function handle(LynxScanner $scanner, ReportGenerator $generator, FindingRepositoryContract $repository): int
    {
        $findings = $scanner->scan();

        if (empty($findings)) {
            $findings = $repository->all();
        }

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

        if ($this->option('json')) {
            $payload = [
                'package' => 'rlash18/lynx-scout',
                'version' => LynxCli::VERSION,
                'generated_at' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
                'summary' => [
                    'total_findings' => count($findings),
                    'critical' => count(array_filter($findings, fn ($f) => $f->getSeverity()->value === 'critical')),
                    'high' => count(array_filter($findings, fn ($f) => $f->getSeverity()->value === 'high')),
                    'medium' => count(array_filter($findings, fn ($f) => $f->getSeverity()->value === 'medium')),
                    'low' => count(array_filter($findings, fn ($f) => $f->getSeverity()->value === 'low')),
                    'info' => count(array_filter($findings, fn ($f) => $f->getSeverity()->value === 'info')),
                ],
                'findings' => array_map(fn ($f): array => $f->toArray(), $findings),
            ];

            foreach (explode("\n", (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) as $jsonLine) {
                $this->line($jsonLine);
            }

            return Command::SUCCESS;
        }

        renderUsing($this->output);

        LynxCli::header('REPORT', 'Performance Intelligence Dossier');
        $health = LynxCli::computeHealth($findings);
        LynxCli::healthbar($health);

        $reportText = $generator->generate($findings);

        foreach (explode("\n", $reportText) as $line) {
            $this->line($line);
        }

        return Command::SUCCESS;
    }
}

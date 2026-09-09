<?php

declare(strict_types=1);

namespace Lynx\Scout\Commands;

use Illuminate\Console\Command;
use Lynx\Scout\Contracts\FindingRepositoryContract;
use Lynx\Scout\Data\Severity;
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
                            {--json : Output report as structured JSON}
                            {--plain : Output unstyled plain text format}';

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

        if ($this->option('plain')) {
            $reportText = $generator->generate($findings);
            foreach (explode("\n", $reportText) as $line) {
                $this->line($line);
            }

            return Command::SUCCESS;
        }

        renderUsing($this->output);

        LynxCli::header('REPORT', 'Lynx Scout Performance Report');
        $health = LynxCli::computeHealth($findings);
        LynxCli::healthbar($health);

        render(<<<'HTML'
            <div class="text-gray-700">────────────────────────────────────────────────────────────────────</div>
        HTML);

        if (empty($findings)) {
            render(<<<'HTML'
                <div class="mt-1">
                    <span class="px-2 bg-green-500 text-black font-bold">[Healthy]</span>
                </div>
            HTML);
            render(<<<'HTML'
                <div class="text-green-400 font-bold mt-1">No performance issues detected. Application runtime is healthy!</div>
                <div class="text-gray-500">All database queries and execution patterns are within optimal thresholds.</div>
                <div class="text-gray-700 mt-1">────────────────────────────────────────────────────────────────────</div>
            HTML);

            return Command::SUCCESS;
        }

        $criticalCount = count(array_filter($findings, fn ($f) => $f->getSeverity()->value === 'critical'));
        $highCount = count(array_filter($findings, fn ($f) => $f->getSeverity()->value === 'high'));
        $mediumCount = count(array_filter($findings, fn ($f) => $f->getSeverity()->value === 'medium'));
        $lowCount = count(array_filter($findings, fn ($f) => $f->getSeverity()->value === 'low'));

        render(<<<HTML
            <div class="mt-1 mb-1">
                <span class="text-gray-400 font-bold">Summary:</span>
                <span class="ml-1 px-1 bg-red-500 text-black font-bold">{$criticalCount} CRITICAL</span>
                <span class="ml-1 px-1 bg-amber-500 text-black font-bold">{$highCount} HIGH</span>
                <span class="ml-1 px-1 bg-cyan-500 text-black font-bold">{$mediumCount} MEDIUM</span>
                <span class="ml-1 px-1 bg-blue-500 text-black font-bold">{$lowCount} LOW</span>
            </div>
            <div class="text-gray-700 mb-1">────────────────────────────────────────────────────────────────────</div>
        HTML);

        $severityGroups = [
            'Critical' => [Severity::Critical, 'bg-red-500'],
            'High' => [Severity::High, 'bg-amber-500'],
            'Medium' => [Severity::Medium, 'bg-cyan-500'],
            'Low' => [Severity::Low, 'bg-blue-500'],
        ];

        $globalIndex = 1;
        foreach ($severityGroups as $groupLabel => [$groupSeverity, $badgeBg]) {
            $groupFindings = array_values(array_filter($findings, fn ($f) => $f->getSeverity() === $groupSeverity));
            if (empty($groupFindings)) {
                continue;
            }

            $count = count($groupFindings);
            $plural = $count === 1 ? '' : 's';

            render(<<<HTML
                <div class="mt-2 mb-1">
                    <span class="px-1.5 {$badgeBg} text-black font-bold uppercase">{$groupLabel}</span>
                    <span class="ml-1 text-gray-300 font-bold">({$count} issue{$plural})</span>
                </div>
            HTML);

            foreach ($groupFindings as $f) {
                $title = htmlspecialchars($f->getTitle(), ENT_QUOTES, 'UTF-8');
                $impact = htmlspecialchars($f->getImpact(), ENT_QUOTES, 'UTF-8');
                $score = number_format($f->getScore(), 1);
                $confidence = (int) ($f->getConfidence() * 100);

                $evidence = $f->getEvidence();
                $route = is_array($evidence) ? ($evidence['route'] ?? ($evidence['uri'] ?? null)) : null;
                $caller = is_array($evidence) ? ($evidence['caller'] ?? null) : null;
                $occurrences = is_array($evidence) ? ($evidence['occurrences'] ?? ($f->getContext()['occurrence_count'] ?? null)) : null;
                $duration = is_array($evidence) ? ($evidence['duration_ms'] ?? ($evidence['total_time_ms'] ?? null)) : null;

                $contextItems = [];
                if ($route !== null) {
                    $safeRoute = htmlspecialchars((string) $route, ENT_QUOTES, 'UTF-8');
                    $contextItems[] = "<span class=\"text-gray-500\">Route:</span>&nbsp;<span class=\"text-cyan-400 font-bold\">{$safeRoute}</span>";
                }
                if ($caller !== null) {
                    $safeCaller = htmlspecialchars((string) $caller, ENT_QUOTES, 'UTF-8');
                    $contextItems[] = "<span class=\"text-gray-500\">Caller:</span>&nbsp;<span class=\"text-yellow-300 font-bold\">{$safeCaller}</span>";
                }

                $queryPattern = is_array($evidence)
                    ? ($evidence['query_pattern'] ?? ($evidence['normalized_sql'] ?? ($evidence['sample_sql'] ?? ($evidence['sql'] ?? null))))
                    : null;
                if ($queryPattern !== null) {
                    $rawQuery = (string) $queryPattern;
                    $trimmedQuery = strlen($rawQuery) > 85 ? substr($rawQuery, 0, 82) . '...' : $rawQuery;
                    $safeQuery = htmlspecialchars($trimmedQuery, ENT_QUOTES, 'UTF-8');
                    $contextItems[] = "<span class=\"text-gray-500\">Query:</span>&nbsp;<span class=\"text-emerald-400\">{$safeQuery}</span>";
                }

                $contextHtml = ! empty($contextItems)
                    ? '&nbsp;&nbsp;' . implode('&nbsp;<span class="text-gray-600">│</span>&nbsp;', $contextItems)
                    : '';

                $metaItems = [];
                $metaItems[] = "<span class=\"text-gray-500\">Estimated Impact:</span>&nbsp;<span class=\"text-amber-400 font-bold\">{$impact}</span>&nbsp;<span class=\"text-gray-400\">(Score: {$score})</span>";
                if ($occurrences !== null) {
                    $metaItems[] = "<span class=\"text-red-400 font-bold\">{$occurrences}x</span>&nbsp;<span class=\"text-gray-500\">queries</span>";
                }
                if ($duration !== null) {
                    $durationFormatted = number_format((float) $duration, 2);
                    $metaItems[] = "<span class=\"text-red-400 font-bold\">{$durationFormatted}ms</span>";
                }
                $metaItems[] = "<span class=\"text-green-400 font-bold\">{$confidence}%</span>&nbsp;<span class=\"text-gray-500\">conf.</span>";

                $metaHtml = '&nbsp;&nbsp;' . implode('&nbsp;<span class="text-gray-600">│</span>&nbsp;', $metaItems);

                $rawRec = (string) ($f->getRecommendation() ?? '');
                $recText = $rawRec;
                $whyText = '';
                $exampleText = '';

                if (preg_match('/(?:^Recommendation:\s*)(.*?)(?:\n\s*Why:|\s+Why:|$)/s', $rawRec, $m)) {
                    $recText = trim($m[1]);
                }
                if (preg_match('/Why:\s*(.*?)(?:\n\s*Example:|\s+Example:|$)/s', $rawRec, $m)) {
                    $whyText = trim($m[1]);
                }
                if (preg_match('/Example:\s*(.*)$/s', $rawRec, $m)) {
                    $exampleText = trim($m[1]);
                }
                $recText = preg_replace('/^Recommendation:\s*/i', '', $recText);

                render(<<<HTML
                    <div class="mt-1">
                        <span class="text-gray-400 font-bold">#{$globalIndex}</span>
                        <span class="ml-1 text-white font-bold">{$title}</span>
                    </div>
                HTML);

                if (! empty($contextHtml)) {
                    render(<<<HTML
                        <div>
                            {$contextHtml}
                        </div>
                    HTML);
                }

                render(<<<HTML
                    <div>
                        {$metaHtml}
                    </div>
                HTML);

                if ($recText !== '') {
                    $safeRecText = htmlspecialchars($recText, ENT_QUOTES, 'UTF-8');
                    render(<<<HTML
                        <div class="mt-1">
                            &nbsp;&nbsp;<span class="text-amber-400 font-bold">Recommendation:</span>&nbsp;<span class="text-white">{$safeRecText}</span>
                        </div>
                    HTML);
                }

                if ($whyText !== '') {
                    $safeWhyText = htmlspecialchars($whyText, ENT_QUOTES, 'UTF-8');
                    render(<<<HTML
                        <div>
                            &nbsp;&nbsp;<span class="text-gray-500 font-bold">Why:</span>&nbsp;<span class="text-gray-400">{$safeWhyText}</span>
                        </div>
                    HTML);
                }

                if ($exampleText !== '') {
                    $safeExampleText = htmlspecialchars($exampleText, ENT_QUOTES, 'UTF-8');
                    render(<<<HTML
                        <div>
                            &nbsp;&nbsp;<span class="text-gray-500 font-bold">Example:</span>&nbsp;<span class="text-cyan-300 font-bold">{$safeExampleText}</span>
                        </div>
                    HTML);
                }

                render(<<<'HTML'
                    <div class="text-gray-800 my-1">────────────────────────────────────────────────────────────────────</div>
                HTML);

                $globalIndex++;
            }
        }

        render(<<<'HTML'
            <div class="mt-2 text-gray-500">
                Tip: Run with <span class="text-amber-400 font-bold">--json</span> for machine-readable pipeline payloads.
            </div>
        HTML);

        return Command::SUCCESS;
    }
}

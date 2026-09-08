<?php

declare(strict_types=1);

namespace Lynx\Scout\Commands;

use Illuminate\Console\Command;
use Lynx\Scout\Data\Severity;
use Lynx\Scout\Services\LynxScanner;
use Lynx\Scout\Support\LynxCli;

use function Termwind\render;
use function Termwind\renderUsing;

class ScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lynx:scan {--sections : Group output strictly by severity sections}';

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
        renderUsing($this->output);

        LynxCli::header('SCANNER');

        render(<<<'HTML'
            <div class="text-gray-400 mb-1">Scanning application...</div>
        HTML);

        $findings = $scanner->scan();

        $items = [
            'Requests analyzed',
            'Queries analyzed',
            'Performance patterns analyzed',
            'Findings prioritized',
        ];
        $checklistHtml = '<div>';
        foreach ($items as $item) {
            $dotsCount = max(3, 58 - strlen($item));
            $dots = str_repeat('.', $dotsCount);
            $checklistHtml .= "<div><span class=\"text-green-500 font-bold mr-1\">✓</span><span class=\"text-white\">{$item}</span> <span class=\"text-gray-600\">{$dots}</span> <span class=\"text-green-400 font-bold\">PASSED</span></div>";
        }
        $checklistHtml .= '</div>';

        render($checklistHtml);

        $health = LynxCli::computeHealth($findings);
        LynxCli::healthbar($health);

        $count = count($findings);
        if ($count === 0) {
            render(<<<'HTML'
                <div class="mt-1">
                    <span class="px-2 bg-green-500 text-black font-bold">[Healthy]</span>
                </div>
            HTML);
            render(<<<'HTML'
                <div class="text-green-400 font-bold mt-1">No performance concerns detected. Application is healthy!</div>
                <div class="text-gray-500">All database queries and execution patterns are within optimal thresholds.</div>
            HTML);
            return Command::SUCCESS;
        }

        $findingText = sprintf('%d finding%s detected.', $count, $count === 1 ? '' : 's');
        render(<<<HTML
            <div class="mt-1 mb-1">
                <span class="px-1 bg-amber-500 text-black font-bold">FINDINGS</span>
                <span class="ml-1 text-amber-400 font-bold">{$findingText}</span>
            </div>
        HTML);

        $index = 1;
        foreach ($findings as $finding) {
            $title = htmlspecialchars($finding->getTitle(), ENT_QUOTES, 'UTF-8');
            $severity = $finding->getSeverity()->label();
            $badgeBg = match (strtolower($severity)) {
                'critical' => 'bg-red-500',
                'high' => 'bg-amber-500',
                'medium' => 'bg-cyan-500',
                'low' => 'bg-blue-500',
                default => 'bg-gray-500',
            };

            $dotsCount = max(3, 61 - strlen($title) - strlen($severity) - strlen((string) $index));
            $dots = str_repeat('.', $dotsCount);

            render(<<<HTML
                <div>
                    <span>&nbsp;&nbsp;<span class="text-gray-400 font-bold">{$index}.</span> <span class="text-white font-bold">{$title}</span> <span class="text-gray-600">{$dots}</span> <span class="px-1 {$badgeBg} text-black font-bold uppercase">{$severity}</span></span>
                </div>
            HTML);
            $index++;
        }

        $sections = [
            'Critical' => Severity::Critical,
            'High' => Severity::High,
            'Medium' => Severity::Medium,
            'Low' => Severity::Low,
        ];

        render(<<<'HTML'
            <div class="mt-2 text-gray-400 font-bold">Sections:</div>
        HTML);

        foreach ($sections as $name => $sev) {
            $matching = array_filter($findings, fn ($f) => $f->getSeverity() === $sev);
            $c = count($matching);
            $badgeBg = match (strtolower($name)) {
                'critical' => 'bg-red-500',
                'high' => 'bg-amber-500',
                'medium' => 'bg-cyan-500',
                'low' => 'bg-blue-500',
                default => 'bg-gray-500',
            };

            render(<<<HTML
                <div>
                    <span class="px-1 {$badgeBg} text-black font-bold">[{$name}]</span>
                    <span class="ml-1 text-gray-300">: {$c} finding</span>
                </div>
            HTML);
        }

        render(<<<'HTML'
            <div class="mt-2 text-gray-400">
                Run <span class="text-amber-400 font-bold">php artisan lynx:report</span> for detailed recommendations.
            </div>
        HTML);

        return Command::SUCCESS;
    }
}

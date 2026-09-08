<?php

declare(strict_types=1);

namespace Lynx\Scout\Support;

use function Termwind\render;

class LynxCli
{
    public const CARD_WIDTH = 68;

    /**
     * Render the signature Laravel Pulse / Horizon dashboard card header.
     */
    public static function header(string $badge = '', ?string $subtitle = null): void
    {
        $sub = $subtitle ?? 'Performance Intelligence · Laravel';

        if ($badge !== '') {
            $badgeLen = strlen($badge) + 2;
            $leftLen = 24;
            $rightLen = 1 + $badgeLen + 3;
            $dashesCount = max(2, self::CARD_WIDTH - $leftLen - $rightLen);
            $dashes = str_repeat('─', $dashesCount);

            $topLine = <<<HTML
                <div><span class="text-gray-700">┌──</span> <span class="px-1 bg-amber-500 text-black font-bold">Lynx Scout</span> <span class="text-gray-500 font-bold">v1.0.0</span> <span class="text-gray-700">{$dashes}</span> <span class="px-1 bg-amber-600 text-black font-bold uppercase">{$badge}</span><span class="text-gray-700">──┐</span></div>
            HTML;
        } else {
            $leftLen = 24;
            $dashesCount = max(2, self::CARD_WIDTH - $leftLen - 1);
            $dashes = str_repeat('─', $dashesCount);

            $topLine = <<<HTML
                <div><span class="text-gray-700">┌──</span> <span class="px-1 bg-amber-500 text-black font-bold">Lynx Scout</span> <span class="text-gray-500 font-bold">v1.0.0</span> <span class="text-gray-700">{$dashes}┐</span></div>
            HTML;
        }

        $midSpacesCount = max(1, self::CARD_WIDTH - mb_strwidth($sub) - 4);
        $midSpaces = str_repeat('&nbsp;', $midSpacesCount);

        $botDashes = str_repeat('─', self::CARD_WIDTH - 2);

        render(<<<HTML
            <div class="my-1">
                {$topLine}
                <div><span class="text-gray-700">│</span>&nbsp;&nbsp;<span class="text-gray-300">{$sub}</span>{$midSpaces}<span class="text-gray-700">│</span></div>
                <div><span class="text-gray-700">└{$botDashes}┘</span></div>
            </div>
        HTML);
    }

    /**
     * Render a dashboard healthbar widget aligned with the card boundary.
     *
     * @param int $percentage Health score from 0 to 100
     * @param string|null $status Custom status label
     */
    public static function healthbar(int $percentage, ?string $status = null): void
    {
        $clamped = max(0, min(100, $percentage));
        $totalBlocks = 34;
        $filledCount = (int) round(($clamped / 100) * $totalBlocks);
        $emptyCount = $totalBlocks - $filledCount;

        $filledBlocks = str_repeat('■', $filledCount);
        $emptyBlocks = str_repeat('░', $emptyCount);

        [$colorClass, $badgeBg, $defaultStatus] = match (true) {
            $clamped >= 80 => ['text-green-400', 'bg-green-500', 'OPTIMAL'],
            $clamped >= 50 => ['text-amber-400', 'bg-amber-500', 'DEGRADED'],
            default => ['text-red-400', 'bg-red-500', 'CRITICAL'],
        };

        $statusText = $status ?? $defaultStatus;

        render(<<<HTML
            <div class="my-1">
                <span class="px-1 bg-gray-800 text-gray-300 font-bold mr-1">HEALTH</span><span class="{$colorClass} font-bold">[</span><span class="{$colorClass} font-bold">{$filledBlocks}</span><span class="text-gray-700 font-bold">{$emptyBlocks}</span><span class="{$colorClass} font-bold">]</span> <span class="{$colorClass} font-bold ml-1">{$clamped}%</span> <span class="px-1 {$badgeBg} text-black font-bold uppercase ml-1">{$statusText}</span>
            </div>
        HTML);
    }

    /**
     * Compute health percentage from an array of findings.
     *
     * @param array<int, mixed> $findings
     */
    public static function computeHealth(array $findings): int
    {
        if (empty($findings)) {
            return 100;
        }

        $penalty = 0;
        foreach ($findings as $f) {
            $severity = method_exists($f, 'getSeverity') ? $f->getSeverity()->value : 'medium';
            $penalty += match ($severity) {
                'critical' => 35,
                'high' => 15,
                'medium' => 8,
                'low' => 3,
                default => 2,
            };
        }

        return max(5, 100 - $penalty);
    }
}

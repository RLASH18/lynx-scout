<?php

declare(strict_types=1);

namespace Lynx\Scout\Support;

class CallerDetector
{
    /**
     * Paths/namespaces to ignore when detecting origin caller.
     *
     * @var list<string>
     */
    private const IGNORED_NAMESPACES = [
        'Illuminate\\',
        'Lynx\\Scout\\',
        'Laravel\\',
        'Symfony\\',
    ];

    /**
     * Detect the application caller location from backtrace.
     */
    public static function detect(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25);

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? null;
            $file = $frame['file'] ?? null;

            if ($class !== null) {
                $ignored = false;
                foreach (self::IGNORED_NAMESPACES as $ns) {
                    if (str_starts_with($class, $ns)) {
                        $ignored = true;
                        break;
                    }
                }

                if (! $ignored) {
                    $func = $frame['function'] ?? '';
                    $line = $frame['line'] ?? 0;
                    return "{$class}@{$func}:{$line}";
                }
            }

            if ($file !== null && ! str_contains($file, 'vendor') && ! str_contains($file, 'src/')) {
                $line = $frame['line'] ?? 0;
                return basename($file) . ":{$line}";
            }
        }

        return null;
    }
}

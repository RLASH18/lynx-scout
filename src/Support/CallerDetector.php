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
        'PHPUnit\\',
        'Orchestra\\',
    ];

    /**
     * Detect the application caller location from backtrace.
     */
    public static function detect(): ?string
    {
        if (! (bool) config('lynx.callers.enabled', true)) {
            return null;
        }

        $sampleRate = (float) config('lynx.callers.sample_rate', 1.0);
        if ($sampleRate < 1.0 && (mt_rand(1, 10000) / 10000.0) > $sampleRate) {
            return null;
        }

        $customIgnored = (array) config('lynx.callers.ignored_namespaces', []);
        $ignoredNamespaces = array_merge(self::IGNORED_NAMESPACES, $customIgnored);

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25);

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? null;
            $file = $frame['file'] ?? null;

            if ($class !== null) {
                $ignored = false;
                foreach ($ignoredNamespaces as $ns) {
                    if (str_starts_with($class, $ns)) {
                        $ignored = true;
                        break;
                    }
                }

                if ($ignored) {
                    continue;
                }

                $func = $frame['function'] ?? '';
                $line = $frame['line'] ?? 0;
                return "{$class}@{$func}:{$line}";
            }

            if ($file !== null && ! str_contains($file, 'vendor') && ! str_contains($file, 'src/')) {
                $line = $frame['line'] ?? 0;
                return basename($file) . ":{$line}";
            }
        }

        return null;
    }
}

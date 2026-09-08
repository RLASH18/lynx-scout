<?php

declare(strict_types=1);

namespace App\Services;

use Lynx\Scout\Support\CallerDetector;

class DummyUserService
{
    public function fetch(): ?string
    {
        return CallerDetector::detect();
    }
}

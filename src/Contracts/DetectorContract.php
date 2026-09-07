<?php

declare(strict_types=1);

namespace Lynx\Scout\Contracts;

use Lynx\Scout\Data\Finding;

interface DetectorContract
{
    /**
     * Analyze collected data and return detected findings.
     *
     * @param array<int, mixed> $records
     * @return list<Finding>
     */
    public function detect(array $records): array;
}

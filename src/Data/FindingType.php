<?php

declare(strict_types=1);

namespace Lynx\Scout\Data;

enum FindingType: string
{
    case SlowQuery = 'slow_query';
    case DuplicateQuery = 'duplicate_query';
    case NPlusOne = 'n_plus_one';
    case SlowRequest = 'slow_request';
    case CacheCandidate = 'cache_candidate';
    case HealthIssue = 'health_issue';
    case SlowJob = 'slow_job';
    case CorrelatedIssue = 'correlated_issue';

    /**
     * Get human-readable title.
     */
    public function label(): string
    {
        return match ($this) {
            self::SlowQuery => 'Slow Database Query',
            self::DuplicateQuery => 'Duplicate Database Query',
            self::NPlusOne => 'N+1 Query Pattern',
            self::SlowRequest => 'Slow HTTP Request',
            self::CacheCandidate => 'Potential Cache Candidate',
            self::HealthIssue => 'Application Health Concern',
            self::SlowJob => 'Slow Queue Job',
            self::CorrelatedIssue => 'Correlated Performance Bottleneck',
        };
    }
}

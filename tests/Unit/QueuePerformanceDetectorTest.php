<?php

declare(strict_types=1);

namespace Lynx\Scout\Tests\Unit;

use DateTimeImmutable;
use Lynx\Scout\Data\FindingType;
use Lynx\Scout\Data\JobRecord;
use Lynx\Scout\Data\Severity;
use Lynx\Scout\Detectors\QueuePerformanceDetector;
use Lynx\Scout\Tests\TestCase;

class QueuePerformanceDetectorTest extends TestCase
{
    private function makeJob(string $name, float $durationMs, bool $failed = false, ?string $exception = null): JobRecord
    {
        return new JobRecord(
            id: bin2hex(random_bytes(4)),
            name: $name,
            connection: 'database',
            queue: 'default',
            durationMs: $durationMs,
            failed: $failed,
            exceptionMessage: $exception,
            executedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Test detector flags jobs that run longer than the configured threshold.
     */
    public function test_detects_slow_jobs(): void
    {
        $detector = new QueuePerformanceDetector(slowThreshold: 2000.0);
        $jobs = [
            $this->makeJob('App\\Jobs\\SendEmail', 350.0),
            $this->makeJob('App\\Jobs\\GeneratePdfReport', 5400.0),
        ];

        $findings = $detector->detect($jobs);
        $this->assertCount(1, $findings);

        $finding = $findings[0];
        $this->assertEquals(FindingType::SlowJob, $finding->getTypeEnum());
        $this->assertEquals(Severity::High, $finding->getSeverity());
        $this->assertEquals('Slow queue job detected', $finding->getTitle());
        $this->assertEquals('App\\Jobs\\GeneratePdfReport', $finding->getEvidence()['job_name']);
        $this->assertEquals(5400.0, $finding->getEvidence()['duration_ms']);
    }

    /**
     * Test detector flags repeated failed jobs.
     */
    public function test_detects_repeated_job_failures(): void
    {
        $detector = new QueuePerformanceDetector();
        $jobs = [
            $this->makeJob('App\\Jobs\\SyncStripeCustomer', 120.0, true, 'Payment gateway timeout'),
            $this->makeJob('App\\Jobs\\SyncStripeCustomer', 115.0, true, 'Payment gateway timeout'),
        ];

        $findings = $detector->detect($jobs);
        $this->assertCount(1, $findings);

        $finding = $findings[0];
        $this->assertEquals(FindingType::HealthIssue, $finding->getTypeEnum());
        $this->assertEquals(Severity::Critical, $finding->getSeverity());
        $this->assertEquals('Repeated queue job failures detected', $finding->getTitle());
        $this->assertEquals(2, $finding->getEvidence()['failures_count']);
    }
}

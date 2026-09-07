<?php

declare(strict_types=1);

namespace Lynx\Scout\Contracts;

use Lynx\Scout\Data\Severity;

interface FindingContract
{
    /**
     * Get unique identifier for the finding.
     */
    public function getId(): string;

    /**
     * Get the finding category type.
     */
    public function getType(): string;

    /**
     * Get severity level.
     */
    public function getSeverity(): Severity;

    /**
     * Get finding short title.
     */
    public function getTitle(): string;

    /**
     * Get detailed description.
     */
    public function getDescription(): string;

    /**
     * Get observed evidence supporting the finding.
     *
     * @return array<string, mixed>|string
     */
    public function getEvidence(): array|string;

    /**
     * Get estimated impact label.
     */
    public function getImpact(): string;

    /**
     * Get actionable recommendation.
     */
    public function getRecommendation(): ?string;

    /**
     * Get detection confidence score between 0.0 and 1.0.
     */
    public function getConfidence(): float;

    /**
     * Get contextual metadata.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array;

    /**
     * Get detection timestamp.
     */
    public function getDetectedAt(): \DateTimeImmutable;

    /**
     * Convert finding to array format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}

<?php

declare(strict_types=1);

namespace Lynx\Scout\Data;

use DateTimeImmutable;
use JsonSerializable;
use Lynx\Scout\Contracts\FindingContract;

class Finding implements FindingContract, JsonSerializable
{
    /**
     * @param array<string, mixed>|string $evidence
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly string $id,
        private readonly string|FindingType $type,
        private readonly Severity $severity,
        private readonly string $title,
        private readonly string $description,
        private readonly array|string $evidence,
        private readonly string $impact = 'Medium',
        private readonly ?string $recommendation = null,
        private readonly float $confidence = 0.85,
        private readonly array $context = [],
        private readonly ?DateTimeImmutable $detectedAt = null,
        private readonly float $score = 0.0,
    ) {}

    /**
     * Create a finding with a generated ID and current timestamp if omitted.
     *
     * @param array<string, mixed>|string $evidence
     * @param array<string, mixed> $context
     */
    public static function create(
        string|FindingType $type,
        Severity $severity,
        string $title,
        string $description,
        array|string $evidence,
        string $impact = 'Medium',
        ?string $recommendation = null,
        float $confidence = 0.85,
        array $context = [],
        ?DateTimeImmutable $detectedAt = null,
        float $score = 0.0,
        ?string $id = null,
    ): self {
        $uniqueId = $id ?? bin2hex(random_bytes(8));
        $timestamp = $detectedAt ?? new DateTimeImmutable();

        return new self(
            id: $uniqueId,
            type: $type,
            severity: $severity,
            title: $title,
            description: $description,
            evidence: $evidence,
            impact: $impact,
            recommendation: $recommendation,
            confidence: min(1.0, max(0.0, $confidence)),
            context: $context,
            detectedAt: $timestamp,
            score: $score,
        );
    }

    /**
     * Get unique finding identifier.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get finding type string.
     */
    public function getType(): string
    {
        return $this->type instanceof FindingType ? $this->type->value : (string) $this->type;
    }

    /**
     * Get typed finding type enum if matched.
     */
    public function getTypeEnum(): ?FindingType
    {
        return $this->type instanceof FindingType
            ? $this->type
            : FindingType::tryFrom((string) $this->type);
    }

    /**
     * Get severity level.
     */
    public function getSeverity(): Severity
    {
        return $this->severity;
    }

    /**
     * Get finding short title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get detailed description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get observed evidence supporting the finding.
     *
     * @return array<string, mixed>|string
     */
    public function getEvidence(): array|string
    {
        return $this->evidence;
    }

    /**
     * Get estimated impact label.
     */
    public function getImpact(): string
    {
        return $this->impact;
    }

    /**
     * Get actionable recommendation.
     */
    public function getRecommendation(): ?string
    {
        return $this->recommendation;
    }

    /**
     * Get detection confidence score between 0.0 and 1.0.
     */
    public function getConfidence(): float
    {
        return $this->confidence;
    }

    /**
     * Get human-readable confidence label.
     */
    public function getConfidenceLabel(): string
    {
        return match (true) {
            $this->confidence >= 0.85 => 'High confidence',
            $this->confidence >= 0.60 => 'Medium confidence',
            default => 'Low confidence',
        };
    }

    /**
     * Get contextual metadata.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get detection timestamp.
     */
    public function getDetectedAt(): DateTimeImmutable
    {
        return $this->detectedAt ?? new DateTimeImmutable();
    }

    /**
     * Get computed numerical impact score.
     */
    public function getScore(): float
    {
        return $this->score;
    }

    /**
     * Return a new instance with an updated recommendation.
     */
    public function withRecommendation(string $recommendation): self
    {
        return new self(
            id: $this->id,
            type: $this->type,
            severity: $this->severity,
            title: $this->title,
            description: $this->description,
            evidence: $this->evidence,
            impact: $this->impact,
            recommendation: $recommendation,
            confidence: $this->confidence,
            context: $this->context,
            detectedAt: $this->detectedAt,
            score: $this->score,
        );
    }

    /**
     * Return a new instance with an updated score and impact.
     */
    public function withScore(float $score, string $impact): self
    {
        return new self(
            id: $this->id,
            type: $this->type,
            severity: $this->severity,
            title: $this->title,
            description: $this->description,
            evidence: $this->evidence,
            impact: $impact,
            recommendation: $this->recommendation,
            confidence: $this->confidence,
            context: $this->context,
            detectedAt: $this->detectedAt,
            score: $score,
        );
    }

    /**
     * Convert finding to array format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'severity' => $this->severity->value,
            'severity_label' => $this->severity->label(),
            'title' => $this->title,
            'description' => $this->description,
            'evidence' => $this->evidence,
            'impact' => $this->impact,
            'recommendation' => $this->recommendation,
            'confidence' => $this->confidence,
            'confidence_label' => $this->getConfidenceLabel(),
            'context' => $this->context,
            'score' => $this->score,
            'detected_at' => $this->getDetectedAt()->format(DateTimeImmutable::ATOM),
        ];
    }

    /**
     * Serialize to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

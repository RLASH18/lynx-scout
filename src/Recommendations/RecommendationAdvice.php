<?php

declare(strict_types=1);

namespace Lynx\Scout\Recommendations;

use JsonSerializable;

class RecommendationAdvice implements JsonSerializable
{
    public function __construct(
        private readonly string $findingTitle,
        private readonly string $recommendation,
        private readonly string $why,
        private readonly ?string $example = null,
        private readonly array $context = [],
    ) {}

    public function getFindingTitle(): string
    {
        return $this->findingTitle;
    }

    public function getRecommendation(): string
    {
        return $this->recommendation;
    }

    public function getWhy(): string
    {
        return $this->why;
    }

    public function getExample(): ?string
    {
        return $this->example;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Convert to structured string following Lynx Scout format.
     */
    public function format(): string
    {
        $out = "Recommendation:\n{$this->recommendation}\n\nWhy:\n{$this->why}";
        if ($this->example !== null) {
            $out .= "\n\nExample:\n{$this->example}";
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'finding' => $this->findingTitle,
            'recommendation' => $this->recommendation,
            'why' => $this->why,
            'example' => $this->example,
            'context' => $this->context,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

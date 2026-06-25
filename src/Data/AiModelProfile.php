<?php

declare(strict_types=1);

namespace Eloquage\LaravelAiModelTiers\Data;

/**
 * Resolved AI provider/model configuration for a tier and task.
 *
 * @phpstan-type FailoverChain array<string, string>
 */
readonly class AiModelProfile
{
    /**
     * @param  FailoverChain|list<string>  $failover
     */
    public function __construct(
        public string $provider,
        public string $model,
        public int $timeout = 60,
        public array $failover = [],
    ) {}

    /**
     * Provider argument for Laravel AI SDK prompt/generate calls.
     *
     * @return string|array<string, string>
     */
    /**
     * @return string|array<string, string>
     */
    public function failoverOrSingle(): string|array
    {
        if ($this->failover === []) {
            return $this->provider;
        }

        /** @var array<string, string> $failover */
        $failover = $this->failover;

        return $failover;
    }
}

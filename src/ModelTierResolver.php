<?php

declare(strict_types=1);

namespace Eloquage\LaravelAiModelTiers;

use Eloquage\LaravelAiModelTiers\Data\AiModelProfile;
use Eloquage\LaravelAiModelTiers\Enums\AiTask;
use Eloquage\LaravelAiModelTiers\Enums\ModelTier;
use InvalidArgumentException;

class ModelTierResolver
{
    public function forEmbeddings(ModelTier|string|null $tier = null): AiModelProfile
    {
        if ($tier === null) {
            $configured = config('ai-model-tiers.embedding_tier', 'cost');
            $tier = is_string($configured) ? $configured : 'cost';
        }

        return $this->resolve(
            is_string($tier) ? ModelTier::tryFromString($tier) ?? ModelTier::Cost : $tier,
            AiTask::Embedding,
        );
    }

    /**
     * @param  array{
     *     token_estimate?: int,
     *     attachment_count?: int,
     *     structured_output?: bool,
     *     legal_document?: bool,
     * }  $context
     */
    public function resolve(
        ModelTier|string|null $tier,
        AiTask|string $task,
        array $context = [],
    ): AiModelProfile {
        $tier = $this->normalizeTier($tier);
        $taskKey = $this->normalizeTask($task);
        $resolvedTier = $this->resolveEffectiveTier($tier, $taskKey, $context);

        return $this->buildProfile($resolvedTier, $taskKey);
    }

    private function normalizeTier(ModelTier|string|null $tier): ModelTier
    {
        if ($tier instanceof ModelTier) {
            return $tier;
        }

        if ($tier === null) {
            $configured = config('ai-model-tiers.default_tier', 'auto');
            $tier = is_string($configured) ? $configured : 'auto';
        }

        return ModelTier::tryFromString($tier) ?? ModelTier::Auto;
    }

    private function normalizeTask(AiTask|string $task): string
    {
        if ($task instanceof AiTask) {
            return $task->value;
        }

        $taskKey = strtolower($task);

        if (AiTask::tryFrom($taskKey) !== null) {
            return $taskKey;
        }

        if (array_key_exists($taskKey, $this->taskDefaults())) {
            return $taskKey;
        }

        throw new InvalidArgumentException("Unknown AI task: {$task}");
    }

    /**
     * @return array<string, string>
     */
    private function taskDefaults(): array
    {
        $defaults = config('ai-model-tiers.task_defaults', []);

        if (! is_array($defaults)) {
            return [];
        }

        $result = [];

        foreach ($defaults as $task => $tier) {
            if (is_string($task) && is_string($tier)) {
                $result[$task] = $tier;
            }
        }

        return $result;
    }

    /**
     * @param  array{
     *     token_estimate?: int,
     *     attachment_count?: int,
     *     structured_output?: bool,
     *     legal_document?: bool,
     * }  $context
     */
    private function resolveEffectiveTier(ModelTier $tier, string $taskKey, array $context): ModelTier
    {
        if ($tier !== ModelTier::Auto) {
            return $tier;
        }

        $taskDefault = $this->taskDefaults()[$taskKey] ?? null;

        if (is_string($taskDefault) && $taskDefault !== 'auto') {
            $fromConfig = ModelTier::tryFromString($taskDefault);

            if ($fromConfig !== null && $fromConfig !== ModelTier::Auto) {
                return $fromConfig;
            }
        }

        return $this->autoHeuristic($taskKey, $context);
    }

    /**
     * @param  array{
     *     token_estimate?: int,
     *     attachment_count?: int,
     *     structured_output?: bool,
     *     legal_document?: bool,
     * }  $context
     */
    private function autoHeuristic(string $taskKey, array $context): ModelTier
    {
        if ($taskKey === AiTask::Embedding->value) {
            $embeddingTier = config('ai-model-tiers.embedding_tier', 'cost');

            return ModelTier::tryFromString(is_string($embeddingTier) ? $embeddingTier : 'cost') ?? ModelTier::Cost;
        }

        $task = AiTask::tryFrom($taskKey);

        if ($task === AiTask::StructuredExtraction || $task === AiTask::Correlation) {
            return ModelTier::Complex;
        }

        if ($task === null) {
            return ModelTier::Cost;
        }

        $tokenEstimate = (int) ($context['token_estimate'] ?? 0);
        $attachmentCount = (int) ($context['attachment_count'] ?? 0);
        $structuredOutput = (bool) ($context['structured_output'] ?? false);
        $legalDocument = (bool) ($context['legal_document'] ?? false);

        $autoConfig = $this->autoConfig();
        $complexThreshold = $autoConfig['complex_token_threshold'];
        $fastThreshold = $autoConfig['fast_token_threshold'];

        if ($structuredOutput || $legalDocument || $tokenEstimate > $complexThreshold || $attachmentCount > 1) {
            return ModelTier::Complex;
        }

        if ($tokenEstimate > 0 && $tokenEstimate < $fastThreshold && $attachmentCount === 0) {
            return ModelTier::Fast;
        }

        return ModelTier::Cost;
    }

    /**
     * @return array{complex_token_threshold: int, fast_token_threshold: int}
     */
    private function autoConfig(): array
    {
        $auto = config('ai-model-tiers.auto', []);

        if (! is_array($auto)) {
            return [
                'complex_token_threshold' => 8000,
                'fast_token_threshold' => 1500,
            ];
        }

        $complex = $auto['complex_token_threshold'] ?? 8000;
        $fast = $auto['fast_token_threshold'] ?? 1500;

        return [
            'complex_token_threshold' => is_numeric($complex) ? (int) $complex : 8000,
            'fast_token_threshold' => is_numeric($fast) ? (int) $fast : 1500,
        ];
    }

    private function buildProfile(ModelTier $tier, string $taskKey): AiModelProfile
    {
        $profileKey = $tier->value;
        $profiles = $this->tierProfiles();

        if (! isset($profiles[$profileKey]) || ! is_array($profiles[$profileKey])) {
            throw new InvalidArgumentException("Unknown tier profile: {$profileKey}");
        }

        /** @var array<string, mixed> $profile */
        $profile = $profiles[$profileKey];

        $kind = $taskKey === AiTask::Embedding->value ? 'embedding' : 'text';
        $kindConfig = $profile[$kind] ?? null;

        if (! is_array($kindConfig)) {
            throw new InvalidArgumentException("Missing {$kind} config for tier: {$profileKey}");
        }

        $provider = isset($kindConfig['provider']) && is_string($kindConfig['provider'])
            ? $kindConfig['provider']
            : '';
        $model = isset($kindConfig['model']) && is_string($kindConfig['model'])
            ? $kindConfig['model']
            : '';

        if ($provider === '' || $model === '') {
            throw new InvalidArgumentException("Incomplete {$kind} config for tier: {$profileKey}");
        }

        $failoverKind = $taskKey === AiTask::Embedding->value ? 'embedding' : 'text';
        $failover = $this->failoverFor($failoverKind, $profileKey);

        $failover = $failover !== [] ? $failover : [$provider => $model];

        $timeout = isset($profile['timeout']) && is_numeric($profile['timeout'])
            ? (int) $profile['timeout']
            : 60;

        return new AiModelProfile(
            provider: $provider,
            model: $model,
            timeout: $timeout,
            failover: $failover,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function tierProfiles(): array
    {
        $profiles = config('ai-model-tiers.profiles', []);

        return is_array($profiles) ? $profiles : [];
    }

    /**
     * @return array<string, string>
     */
    private function failoverFor(string $kind, string $profileKey): array
    {
        $failover = config("ai-model-tiers.failover.{$kind}.{$profileKey}", []);

        if (! is_array($failover)) {
            return [];
        }

        $result = [];

        foreach ($failover as $provider => $model) {
            if (is_string($provider) && is_string($model)) {
                $result[$provider] = $model;
            }
        }

        return $result;
    }
}

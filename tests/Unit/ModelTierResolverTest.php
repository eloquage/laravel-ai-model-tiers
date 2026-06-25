<?php

use Eloquage\LaravelAiModelTiers\Data\AiModelProfile;
use Eloquage\LaravelAiModelTiers\Enums\AiTask;
use Eloquage\LaravelAiModelTiers\Enums\ModelTier;
use Eloquage\LaravelAiModelTiers\ModelTierResolver;

beforeEach(function () {
    config([
        'ai-model-tiers' => [
            'embedding_tier' => 'cost',
            'task_defaults' => [
                'translation' => 'complex',
                'ocr_vision' => 'complex',
                'embedding' => 'cost',
                'summarization' => 'auto',
            ],
            'custom_tasks' => [],
            'profiles' => [
                'cost' => [
                    'text' => ['provider' => 'deepseek', 'model' => 'deepseek-chat'],
                    'embedding' => ['provider' => 'ollama', 'model' => 'nomic-embed-text'],
                    'timeout' => 60,
                ],
                'fast' => [
                    'text' => ['provider' => 'deepseek', 'model' => 'fast-model'],
                    'embedding' => ['provider' => 'ollama', 'model' => 'nomic-embed-text'],
                    'timeout' => 45,
                ],
                'complex' => [
                    'text' => ['provider' => 'deepseek', 'model' => 'complex-model'],
                    'embedding' => ['provider' => 'ollama', 'model' => 'nomic-embed-text'],
                    'timeout' => 180,
                ],
            ],
            'failover' => [
                'text' => [
                    'complex' => ['deepseek' => 'complex-model', 'openai' => 'gpt-4.1'],
                ],
                'embedding' => [
                    'cost' => ['ollama' => 'nomic-embed-text'],
                ],
            ],
            'auto' => [
                'complex_token_threshold' => 8000,
                'fast_token_threshold' => 1500,
            ],
        ],
    ]);
});

it('resolves cost tier for text tasks', function () {
    $profile = (new ModelTierResolver)->resolve(ModelTier::Cost, AiTask::Translation);

    expect($profile)->toBeInstanceOf(AiModelProfile::class)
        ->and($profile->provider)->toBe('deepseek')
        ->and($profile->model)->toBe('deepseek-chat')
        ->and($profile->timeout)->toBe(60);
});

it('resolves complex tier with failover chain', function () {
    $profile = (new ModelTierResolver)->resolve(ModelTier::Complex, AiTask::Translation);

    expect($profile->model)->toBe('complex-model')
        ->and($profile->failoverOrSingle())->toBe([
            'deepseek' => 'complex-model',
            'openai' => 'gpt-4.1',
        ]);
});

it('auto selects complex for large token estimates', function () {
    $profile = (new ModelTierResolver)->resolve(ModelTier::Auto, AiTask::Translation, [
        'token_estimate' => 12000,
    ]);

    expect($profile->model)->toBe('complex-model');
});

it('auto selects fast for short text without attachments', function () {
    $profile = (new ModelTierResolver)->resolve(ModelTier::Auto, AiTask::Summarization, [
        'token_estimate' => 500,
        'attachment_count' => 0,
    ]);

    expect($profile->model)->toBe('fast-model');
});

it('auto selects cost as default fallback', function () {
    $profile = (new ModelTierResolver)->resolve(ModelTier::Auto, AiTask::Summarization, [
        'token_estimate' => 3000,
        'attachment_count' => 0,
    ]);

    expect($profile->model)->toBe('deepseek-chat');
});

it('forEmbeddings uses embedding tier profile', function () {
    $profile = (new ModelTierResolver)->forEmbeddings();

    expect($profile->provider)->toBe('ollama')
        ->and($profile->model)->toBe('nomic-embed-text');
});

it('respects task_defaults for auto translation', function () {
    $profile = (new ModelTierResolver)->resolve(ModelTier::Auto, AiTask::Translation, [
        'token_estimate' => 100,
    ]);

    expect($profile->model)->toBe('complex-model');
});

it('auto selects complex for legal documents via heuristic', function () {
    config(['ai-model-tiers.task_defaults.summarization' => 'auto']);

    $profile = app(ModelTierResolver::class)->resolve(
        ModelTier::Auto,
        AiTask::Summarization,
        ['legal_document' => true],
    );

    expect($profile->model)->toBe('complex-model');
});

it('auto selects complex for translation with legal document context', function () {
    $profile = app(ModelTierResolver::class)->resolve(
        ModelTier::Auto,
        AiTask::Translation,
        ['legal_document' => true],
    );

    expect($profile->model)->toBe('complex-model');
});

it('resolves ModelTierResolver as a singleton from the container', function () {
    $first = app(ModelTierResolver::class);
    $second = app(ModelTierResolver::class);

    expect($first)->toBe($second)
        ->and($first->resolve(ModelTier::Cost, AiTask::Translation)->model)->toBe('deepseek-chat');
});

it('resolves custom string tasks via custom_tasks config', function () {
    config([
        'ai-model-tiers.custom_tasks' => [
            'my_report' => 'fast',
        ],
        'ai-model-tiers.task_defaults' => array_merge(
            config('ai-model-tiers.task_defaults', []),
            ['my_report' => 'fast'],
        ),
    ]);

    $profile = (new ModelTierResolver)->resolve(ModelTier::Auto, 'my_report');

    expect($profile->model)->toBe('fast-model');
});

it('throws for unknown custom string tasks', function () {
    (new ModelTierResolver)->resolve(ModelTier::Auto, 'unknown_task');
})->throws(InvalidArgumentException::class, 'Unknown AI task: unknown_task');

it('resolves local tier to gemini for commit_message task', function () {
    config([
        'ai-model-tiers.custom_tasks' => [
            'commit_message' => 'local',
        ],
        'ai-model-tiers.task_defaults' => array_merge(
            config('ai-model-tiers.task_defaults', []),
            ['commit_message' => 'local'],
        ),
        'ai-model-tiers.profiles.local' => [
            'text' => ['provider' => 'gemini', 'model' => 'gemini-2.5-flash-lite'],
            'embedding' => ['provider' => 'ollama', 'model' => 'nomic-embed-text'],
            'timeout' => 120,
        ],
        'ai-model-tiers.failover.text.local' => [
            'gemini' => 'gemini-2.5-flash-lite',
        ],
    ]);

    $profile = (new ModelTierResolver)->resolve(ModelTier::Auto, 'commit_message');

    expect($profile->provider)->toBe('gemini')
        ->and($profile->model)->toBe('gemini-2.5-flash-lite')
        ->and($profile->failoverOrSingle())->toBe(['gemini' => 'gemini-2.5-flash-lite']);
});

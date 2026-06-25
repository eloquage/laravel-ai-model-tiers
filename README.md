# Laravel AI Model Tiers

Model tier resolution (`auto`, `cost`, `fast`, `complex`) with task-aware heuristics and provider failover chains.
Designed to pair with [Laravel AI SDK](https://github.com/laravel/ai) — this package resolves **which** provider/model
to use; your app passes the result into AI SDK calls.

## Installation

```bash
composer require eloquage/laravel-ai-model-tiers
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag="laravel-ai-model-tiers-config"
```

## Usage

```php
use Eloquage\LaravelAiModelTiers\Enums\AiTask;
use Eloquage\LaravelAiModelTiers\ModelTierResolver;

$profile = app(ModelTierResolver::class)->resolve('auto', AiTask::Translation, [
    'token_estimate' => (int) (strlen($text) / 4),
    'legal_document' => true,
]);

$response = $agent->prompt(
    $text,
    provider: $profile->failoverOrSingle(),
    model: $profile->model,
    timeout: $profile->timeout,
);
```

### Embeddings

```php
$profile = app(ModelTierResolver::class)->forEmbeddings();

$embedding = Embeddings::for($text)
    ->generate(provider: $profile->failoverOrSingle(), model: $profile->model);
```

## Tiers

| Tier      | Purpose                                           |
|-----------|---------------------------------------------------|
| `auto`    | Resolves via task defaults and context heuristics |
| `cost`    | Default economical text model                     |
| `fast`    | Low-latency model for short, simple tasks         |
| `complex` | High-capability model for demanding work          |

## Context keys

When using `ModelTier::Auto`, pass optional context to influence heuristics:

| Key                 | Type   | Effect                                      |
|---------------------|--------|---------------------------------------------|
| `token_estimate`    | `int`  | Large values → complex; small values → fast |
| `attachment_count`  | `int`  | More than one → complex                     |
| `structured_output` | `bool` | `true` → complex                            |
| `legal_document`    | `bool` | `true` → complex                            |

## Built-in tasks

`AiTask` enum: `Translation`, `OcrVision`, `Embedding`, `Summarization`, `Correlation`, `StructuredExtraction`.

Per-task defaults are configured under `task_defaults` in `config/ai-model-tiers.php`.

## Custom tasks

Add app-specific tasks without modifying the enum:

```php
// config/ai-model-tiers.php
'custom_tasks' => [
    'my_report' => 'fast',
],
```

Custom tasks are merged into `task_defaults` at boot. Pass the task as a string:

```php
$resolver->resolve('auto', 'my_report');
```

Auto heuristics apply only to built-in `AiTask` enum cases. Unknown custom tasks default to the `cost` tier unless
overridden in `custom_tasks`.

## Configuration

Key settings in `config/ai-model-tiers.php`:

- `default_tier` — fallback when no tier is specified
- `embedding_tier` — tier used by `forEmbeddings()`
- `task_defaults` — per-task tier overrides for `auto` resolution
- `profiles` — provider/model/timeout per tier (`text` and `embedding` kinds)
- `failover` — provider failover chains per tier and kind
- `auto` — token thresholds for heuristic resolution

Provider strings (`deepseek`, `openai`, etc.) must match your Laravel AI SDK provider configuration.

## Testing

```bash
composer test
composer analyse
composer format
```

## License

MIT.

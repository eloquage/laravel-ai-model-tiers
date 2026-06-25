<?php

declare(strict_types=1);

return [

    'default_tier' => env('AI_DEFAULT_TIER', 'auto'),

    'embedding_tier' => env('AI_EMBEDDING_TIER', 'cost'),

    'task_defaults' => [
        'translation' => env('AI_TRANSLATION_TIER', 'complex'),
        'ocr_vision' => env('AI_OCR_TIER', 'complex'),
        'embedding' => env('AI_EMBEDDING_TIER', 'cost'),
        'summarization' => 'fast',
        'correlation' => 'complex',
        'structured_extraction' => 'complex',
    ],

    'custom_tasks' => [
        // 'my_report' => 'fast',
    ],

    'profiles' => [
        'cost' => [
            'text' => ['provider' => 'deepseek', 'model' => 'deepseek-chat'],
            'embedding' => ['provider' => 'ollama', 'model' => 'nomic-embed-text'],
            'timeout' => 60,
        ],
        'fast' => [
            'text' => ['provider' => 'deepseek', 'model' => 'deepseek-chat'],
            'embedding' => ['provider' => 'ollama', 'model' => 'nomic-embed-text'],
            'timeout' => 45,
        ],
        'complex' => [
            'text' => ['provider' => 'deepseek', 'model' => 'deepseek-chat'],
            'embedding' => ['provider' => 'ollama', 'model' => 'nomic-embed-text'],
            'timeout' => 180,
        ],
    ],

    'failover' => [
        'text' => [
            'complex' => [
                'deepseek' => 'deepseek-chat',
                'anthropic' => 'claude-sonnet-4-20250514',
                'openai' => 'gpt-4.1',
            ],
            'fast' => [
                'deepseek' => 'deepseek-chat',
                'gemini' => 'gemini-2.0-flash',
            ],
            'cost' => [
                'deepseek' => 'deepseek-chat',
                'ollama' => 'llama3.2',
            ],
        ],
        'embedding' => [
            'cost' => [
                'ollama' => 'nomic-embed-text',
                'openai' => 'text-embedding-3-small',
            ],
        ],
    ],

    'auto' => [
        'complex_token_threshold' => 8000,
        'fast_token_threshold' => 1500,
    ],

];

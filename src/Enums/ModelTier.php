<?php

declare(strict_types=1);

namespace Eloquage\LaravelAiModelTiers\Enums;

enum ModelTier: string
{
    case Auto = 'auto';
    case Cost = 'cost';
    case Fast = 'fast';
    case Complex = 'complex';

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom(strtolower($value));
    }
}

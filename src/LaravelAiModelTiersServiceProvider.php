<?php

declare(strict_types=1);

namespace Eloquage\LaravelAiModelTiers;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelAiModelTiersServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-ai-model-tiers')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ModelTierResolver::class);
    }

    public function packageBooted(): void
    {
        $defaults = config('ai-model-tiers.task_defaults', []);
        $custom = config('ai-model-tiers.custom_tasks', []);

        if (! is_array($defaults)) {
            $defaults = [];
        }

        if (! is_array($custom)) {
            $custom = [];
        }

        if ($custom !== []) {
            config(['ai-model-tiers.task_defaults' => array_merge($defaults, $custom)]);
        }
    }
}

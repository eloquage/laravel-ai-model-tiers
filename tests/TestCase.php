<?php

namespace Eloquage\LaravelAiModelTiers\Tests;

use Eloquage\LaravelAiModelTiers\LaravelAiModelTiersServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelAiModelTiersServiceProvider::class,
        ];
    }
}

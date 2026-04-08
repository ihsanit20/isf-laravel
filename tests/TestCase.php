<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PHPUnit\Framework\SkippedTestError;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! in_array($feature, config('fortify.features', []), true)) {
            throw new SkippedTestError($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}

<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * A streamed design assistant reply calls set_time_limit(), which applies
     * to the whole test process; without a reset every test that runs more
     * than that long afterwards dies with a fatal error.
     */
    protected function tearDown(): void
    {
        set_time_limit(0);

        parent::tearDown();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}

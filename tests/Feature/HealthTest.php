<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Provides health test behavior within the WorkIntel application. */ class HealthTest extends TestCase
{
    /** Handles the test health endpoint is available operation for the current WorkIntel workflow. */ public function test_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertSuccessful();
    }
}

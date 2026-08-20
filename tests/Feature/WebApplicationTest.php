<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Provides web application test behavior within the WorkIntel application. */ class WebApplicationTest extends TestCase
{
    /** Handles the test react application shell is served operation for the current WorkIntel workflow. */ public function test_react_application_shell_is_served(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="root"', false);

        $this->get('/projects/demo')
            ->assertOk()
            ->assertSee('id="root"', false);
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_health_check_returns_a_successful_response()
    {
        $response = $this->get('/up');

        $response->assertOk();
    }
}
